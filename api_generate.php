<?php
/**
 * API Endpoint: AI Generation & Slug Checking
 */
require_once __DIR__ . '/auth.php';

// Handle GET for slug checking
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'check_slug') {
    $slug = strtolower(trim($_GET['slug'] ?? ''));
    $pageId = (int)($_GET['page_id'] ?? 0);

    if (!isValidSlug($slug)) {
        jsonResponse(['available' => false, 'reason' => 'Invalid format. Use letters, numbers, hyphens, underscores (min 3 chars).']);
    }

    $reserved = unserialize(RESERVED_SLUGS);
    if (in_array($slug, $reserved)) {
        jsonResponse(['available' => false, 'reason' => 'This is a reserved word.']);
    }

    $available = isSlugAvailable($slug, $pageId ?: null);
    jsonResponse(['available' => $available, 'reason' => $available ? null : 'Already taken.']);
}

// Handle POST for AI generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $user = currentUser();
    if (!$user || !$user['email_verified']) {
        jsonResponse(['success' => false, 'error' => 'Please log in and verify your email.'], 401);
    }

    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid request.'], 403);
    }

    $prompt = trim($_POST['prompt'] ?? '');
    $resumeText = '';

    // Handle file upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resume'];
        $allowed = unserialize(ALLOWED_RESUME_TYPES);

        if (!in_array($file['type'], $allowed)) {
            jsonResponse(['success' => false, 'error' => 'Unsupported file type.'], 400);
        }

        if ($file['size'] > MAX_RESUME_SIZE) {
            jsonResponse(['success' => false, 'error' => 'File too large (max 5MB).'], 400);
        }

        // Extract text from uploaded file
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext === 'txt') {
            $resumeText = file_get_contents($file['tmp_name']);
        } elseif ($ext === 'pdf') {
            // Try to extract text from PDF using pdftotext if available
            $tmpOut = tempnam(sys_get_temp_dir(), 'resume_');
            exec('pdftotext ' . escapeshellarg($file['tmp_name']) . ' ' . escapeshellarg($tmpOut) . ' 2>/dev/null');
            if (file_exists($tmpOut)) {
                $resumeText = file_get_contents($tmpOut);
                unlink($tmpOut);
            }
            if (empty(trim($resumeText))) {
                $resumeText = '[PDF uploaded: ' . $file['name'] . ' - Could not extract text. Please paste resume content manually.]';
            }
        } elseif (in_array($ext, ['doc', 'docx'])) {
            // Basic text extraction for docx
            if ($ext === 'docx') {
                $zip = new ZipArchive();
                if ($zip->open($file['tmp_name']) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml) {
                        $resumeText = strip_tags(str_replace('<', ' <', $xml));
                        $resumeText = preg_replace('/\s+/', ' ', $resumeText);
                    }
                }
            }
            if (empty(trim($resumeText))) {
                $resumeText = '[Document uploaded: ' . $file['name'] . ' - Could not extract text. Please paste resume content manually.]';
            }
        }
    }

    // Build the full prompt for AI
    $fullContent = '';
    if ($resumeText) {
        $fullContent .= "RESUME CONTENT:\n" . $resumeText . "\n\n";
    }
    if ($prompt) {
        $fullContent .= "USER INSTRUCTIONS:\n" . $prompt;
    }

    if (!$fullContent) {
        jsonResponse(['success' => false, 'error' => 'Please provide some content or upload a resume.'], 400);
    }

    // Call Anthropic API
    $html = callAnthropicAPI($fullContent);

    if ($html === false) {
        jsonResponse(['success' => false, 'error' => 'Failed to generate page. Please try again.'], 500);
    }

    // Sanitize the generated HTML
    $html = sanitizePageHtml($html);

    jsonResponse(['success' => true, 'html' => $html]);
}

// 404 for anything else
jsonResponse(['error' => 'Not found.'], 404);

/**
 * Call Anthropic API to generate one-page HTML
 */
function callAnthropicAPI(string $content): string|false {
    $systemPrompt = <<<'PROMPT'
You are a professional web designer creating a single-page personal profile / portfolio page. 
The user will provide their resume content or description of themselves.

Generate a COMPLETE, self-contained HTML page (just the body content, no <html>, <head>, <body>, or <script> tags) 
that creates a stunning, modern one-page profile suitable for sharing with recruiters.

DESIGN REQUIREMENTS:
- Use inline styles or a single <style> tag at the top
- Modern, clean design with excellent typography
- Use Google Fonts (import via @import in style tag): choose distinctive, professional fonts
- Responsive design that works on mobile and desktop
- Light, professional color scheme with subtle accent colors
- Generous whitespace and clear visual hierarchy
- Big, bold headings. Professional but not boring.
- Sections for: Hero/Introduction, Skills/Expertise, Experience, Education (as applicable)
- Smooth, elegant layout with cards or sections
- Include subtle visual elements like borders, shadows, gradients where appropriate
- Make it feel like a premium, designed page — NOT a generic template
- NO JavaScript, NO forms, NO interactive elements, NO external images
- Use CSS-only decorative elements (gradients, shapes, borders) for visual interest
- Use emoji or Unicode symbols for icons if needed
- Total HTML should be under 50KB

OUTPUT: Return ONLY the HTML content. No explanation, no markdown, no code fences. Just raw HTML starting with <style> or <div>.
PROMPT;

    $data = [
        'model' => ANTHROPIC_MODEL,
        'max_tokens' => 8000,
        'system' => $systemPrompt,
        'messages' => [
            ['role' => 'user', 'content' => $content]
        ]
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Anthropic API curl error: " . $curlError);
        return false;
    }

    if ($httpCode !== 200) {
        error_log("Anthropic API error ($httpCode): " . $response);
        return false;
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result['content'][0]['text'])) {
        error_log("Anthropic API unexpected response: " . $response);
        return false;
    }

    $html = $result['content'][0]['text'];

    // Strip any markdown code fences if present
    $html = preg_replace('/^```html?\s*/i', '', $html);
    $html = preg_replace('/\s*```\s*$/', '', $html);

    // Remove full HTML document wrapper if present
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        $html = $m[1];
    }
    // Keep style tags that might be before body content
    if (preg_match('/(<style[^>]*>.*?<\/style>)/is', $html, $styleMatch)) {
        $styleBlock = $styleMatch[1];
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = $styleBlock . "\n" . $html;
    }

    // Remove dangerous tags one more time
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);

    return trim($html);
}
