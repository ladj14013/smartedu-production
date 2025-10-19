<?php
/**
 * KaTeX Configuration for Math Equations
 * ملف إعدادات KaTeX لعرض المعادلات الرياضية
 */

// دالة لإضافة KaTeX CSS في <head>
function include_katex_css() {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" integrity="sha384-n8MVd4RsNIU0tAv4ct0nTaAbDJwPJzDEaqSD1odI+WdtXRGWt2kTvGFasHpSy3SV" crossorigin="anonymous">' . "\n";
}

// دالة لإضافة KaTeX JS قبل </body>
function include_katex_js() {
    ?>
    <!-- KaTeX JS for Math Equations -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js" integrity="sha384-XjKyOOlGwcjNTAIQHIpgOno0Hl1YQqzUOEleOLALmuqehneUG+vnGctmUb0ZY0l8" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js" integrity="sha384-+VBxd3r6XgURycqtZ117nYw44OOcIax56Z4dCRWbxyPt0Koah1uHoK0o4+/RRE05" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Render all math equations in the page
            renderMathInElement(document.body, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},   // Display math (centered)
                    {left: '$', right: '$', display: false},    // Inline math
                    {left: '\\[', right: '\\]', display: true}, // LaTeX display
                    {left: '\\(', right: '\\)', display: false} // LaTeX inline
                ],
                throwOnError: false,
                errorColor: '#cc0000',
                trust: true
            });
        });
    </script>
    <?php
}

// دالة للتحقق من وجود معادلات في النص
function has_math_equations($text) {
    if (empty($text)) return false;
    
    // البحث عن صيغ LaTeX
    $patterns = [
        '/\$\$.+?\$\$/s',     // Display math
        '/\$.+?\$/s',         // Inline math
        '/\\\\\[.+?\\\\\]/s', // LaTeX display
        '/\\\\\(.+?\\\\\)/s'  // LaTeX inline
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    
    return false;
}

// دالة لعد المعادلات في النص
function count_math_equations($text) {
    if (empty($text)) return 0;
    
    $count = 0;
    $patterns = [
        '/\$\$.+?\$\$/s',
        '/\$.+?\$/s',
        '/\\\\\[.+?\\\\\]/s',
        '/\\\\\(.+?\\\\\)/s'
    ];
    
    foreach ($patterns as $pattern) {
        $count += preg_match_all($pattern, $text, $matches);
    }
    
    return $count;
}
