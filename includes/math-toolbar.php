<!-- Math Symbols Toolbar Component -->
<div class="math-toolbar" id="mathToolbar" style="display: none;">
    <div class="math-toolbar-header">
        <h4>🔢 لوحة الرموز الرياضية</h4>
        <button type="button" class="close-toolbar" onclick="closeMathToolbar()">✕</button>
    </div>
    
    <div class="math-toolbar-tabs">
        <button class="math-tab active" onclick="showMathTab('basic')">العمليات</button>
        <button class="math-tab" onclick="showMathTab('powers')">القوى والجذور</button>
        <button class="math-tab" onclick="showMathTab('greek')">الحروف اليونانية</button>
        <button class="math-tab" onclick="showMathTab('calculus')">التحليل</button>
        <button class="math-tab" onclick="showMathTab('special')">رموز خاصة</button>
        <button class="math-tab" onclick="showMathTab('latex')">LaTeX</button>
    </div>
    
    <div class="math-toolbar-content">
        <!-- Basic Operations Tab -->
        <div class="math-tab-panel active" id="tab-basic">
            <div class="math-symbols-grid">
                <button type="button" class="math-symbol" onclick="insertSymbol('+')">+</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('−')">−</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('×')">×</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('÷')">÷</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('=')">= </button>
                <button type="button" class="math-symbol" onclick="insertSymbol('≠')">≠</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('<')">&lt;</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('>')">&gt;</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('≤')">≤</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('≥')">≥</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('±')">±</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∓')">∓</button>
            </div>
        </div>
        
        <!-- Powers and Roots Tab -->
        <div class="math-tab-panel" id="tab-powers">
            <div class="math-symbols-grid">
                <button type="button" class="math-symbol" onclick="insertSymbol('²')">x²</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('³')">x³</button>
                <button type="button" class="math-symbol" onclick="insertLatex('x^{n}')">xⁿ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('√')">√</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\sqrt{x}')">√x</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\sqrt[3]{x}')">∛x</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('½')">½</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('⅓')">⅓</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('¼')">¼</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('⅔')">⅔</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('¾')">¾</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\frac{a}{b}')">a/b</button>
            </div>
        </div>
        
        <!-- Greek Letters Tab -->
        <div class="math-tab-panel" id="tab-greek">
            <div class="math-symbols-grid">
                <button type="button" class="math-symbol" onclick="insertSymbol('α')">α</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('β')">β</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('γ')">γ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('δ')">δ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('ε')">ε</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('θ')">θ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('λ')">λ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('μ')">μ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('π')">π</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('ρ')">ρ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('σ')">σ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('ω')">ω</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('Δ')">Δ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('Σ')">Σ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('Ω')">Ω</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('Φ')">Φ</button>
            </div>
        </div>
        
        <!-- Calculus Tab -->
        <div class="math-tab-panel" id="tab-calculus">
            <div class="math-symbols-grid">
                <button type="button" class="math-symbol" onclick="insertSymbol('∫')">∫</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\int_{a}^{b}')">∫ᵃᵇ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∑')">∑</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\sum_{i=1}^{n}')">∑ⁿ</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∏')">∏</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\lim_{x\\to\\infty}')">lim</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('→')">→</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∞')">∞</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∂')">∂</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∇')">∇</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\frac{d}{dx}')">d/dx</button>
                <button type="button" class="math-symbol" onclick="insertLatex('\\frac{\\partial}{\\partial x}')">∂/∂x</button>
            </div>
        </div>
        
        <!-- Special Symbols Tab -->
        <div class="math-tab-panel" id="tab-special">
            <div class="math-symbols-grid">
                <button type="button" class="math-symbol" onclick="insertSymbol('(')">( )</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('[')">[ ]</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('{')">{ }</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('|')">| |</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∠')">∠</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('°')">°</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('′')">′</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('″')">″</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('⊥')">⊥</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∥')">∥</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∈')">∈</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∉')">∉</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∪')">∪</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('∩')">∩</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('⊂')">⊂</button>
                <button type="button" class="math-symbol" onclick="insertSymbol('⊃')">⊃</button>
            </div>
        </div>
        
        <!-- LaTeX Templates Tab -->
        <div class="math-tab-panel" id="tab-latex">
            <div class="math-templates">
                <button type="button" class="math-template" onclick="insertLatex('\\frac{a}{b}')">
                    <span class="template-preview">كسر</span>
                    <code>\frac{a}{b}</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('\\sqrt{x}')">
                    <span class="template-preview">جذر</span>
                    <code>\sqrt{x}</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('x^{n}')">
                    <span class="template-preview">قوة</span>
                    <code>x^{n}</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('x_{i}')">
                    <span class="template-preview">منخفض</span>
                    <code>x_{i}</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('\\int_{a}^{b} f(x) dx')">
                    <span class="template-preview">تكامل</span>
                    <code>\int_{a}^{b} f(x) dx</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('\\sum_{i=1}^{n} x_i')">
                    <span class="template-preview">مجموع</span>
                    <code>\sum_{i=1}^{n} x_i</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('\\lim_{x \\to \\infty} f(x)')">
                    <span class="template-preview">نهاية</span>
                    <code>\lim_{x \to \infty} f(x)</code>
                </button>
                <button type="button" class="math-template" onclick="insertLatex('\\begin{matrix} a & b \\\\ c & d \\end{matrix}')">
                    <span class="template-preview">مصفوفة</span>
                    <code>\begin{matrix}</code>
                </button>
            </div>
        </div>
    </div>
    
    <div class="math-toolbar-footer">
        <p><strong>نصيحة:</strong> استخدم $ للمعادلات السطرية، و $$ للمعادلات المنفصلة</p>
        <p><em>مثال:</em> <code>$x^2 + 2x + 1$</code> أو <code>$$\int_0^1 x^2 dx$$</code></p>
    </div>
</div>

<style>
.math-toolbar {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    z-index: 9999;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.math-toolbar-header {
    padding: 20px;
    border-bottom: 2px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px 16px 0 0;
    color: white;
}

.math-toolbar-header h4 {
    margin: 0;
    font-size: 1.2rem;
}

.close-toolbar {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 1.5rem;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
}

.close-toolbar:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.math-toolbar-tabs {
    display: flex;
    padding: 10px 20px 0;
    gap: 5px;
    background: #f9fafb;
    overflow-x: auto;
}

.math-tab {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s;
    white-space: nowrap;
}

.math-tab:hover {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.math-tab.active {
    background: white;
    color: #667eea;
    box-shadow: 0 -2px 5px rgba(0,0,0,0.05);
}

.math-toolbar-content {
    padding: 20px;
    max-height: 300px;
    overflow-y: auto;
}

.math-tab-panel {
    display: none;
}

.math-tab-panel.active {
    display: block;
}

.math-symbols-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
    gap: 10px;
}

.math-symbol {
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.math-symbol:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.math-symbol:active {
    transform: translateY(0);
}

.math-templates {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}

.math-template {
    padding: 15px;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: right;
}

.math-template:hover {
    border-color: #667eea;
    background: #f0f4ff;
    transform: translateY(-2px);
}

.template-preview {
    display: block;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.math-template code {
    display: block;
    font-size: 0.85rem;
    color: #667eea;
    background: white;
    padding: 5px 8px;
    border-radius: 4px;
    margin-top: 5px;
}

.math-toolbar-footer {
    padding: 15px 20px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 16px 16px;
}

.math-toolbar-footer p {
    margin: 5px 0;
    font-size: 0.9rem;
    color: #6b7280;
}

.math-toolbar-footer code {
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
    color: #667eea;
    font-family: 'Courier New', monospace;
}

@media (max-width: 768px) {
    .math-toolbar {
        bottom: 0;
        left: 0;
        right: 0;
        border-radius: 16px 16px 0 0;
        max-height: 80vh;
    }
    
    .math-symbols-grid {
        grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
    }
}
</style>

<script>
let currentTextarea = null;

function showMathToolbar(textareaId) {
    currentTextarea = document.getElementById(textareaId);
    document.getElementById('mathToolbar').style.display = 'block';
}

function closeMathToolbar() {
    document.getElementById('mathToolbar').style.display = 'none';
    currentTextarea = null;
}

function showMathTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.math-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.math-tab-panel').forEach(panel => panel.classList.remove('active'));
    
    // Show selected tab
    event.target.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

function insertSymbol(symbol) {
    if (!currentTextarea) return;
    
    const start = currentTextarea.selectionStart;
    const end = currentTextarea.selectionEnd;
    const text = currentTextarea.value;
    
    currentTextarea.value = text.substring(0, start) + symbol + text.substring(end);
    currentTextarea.selectionStart = currentTextarea.selectionEnd = start + symbol.length;
    currentTextarea.focus();
}

function insertLatex(latex) {
    if (!currentTextarea) return;
    
    const start = currentTextarea.selectionStart;
    const end = currentTextarea.selectionEnd;
    const text = currentTextarea.value;
    
    // Wrap in $ if not already wrapped
    const wrapped = '$' + latex + '$';
    
    currentTextarea.value = text.substring(0, start) + wrapped + text.substring(end);
    currentTextarea.selectionStart = currentTextarea.selectionEnd = start + wrapped.length;
    currentTextarea.focus();
}

// Close toolbar when clicking outside
document.addEventListener('click', function(event) {
    const toolbar = document.getElementById('mathToolbar');
    const isClickInside = toolbar && toolbar.contains(event.target);
    const isMathButton = event.target.classList.contains('open-math-toolbar');
    
    if (!isClickInside && !isMathButton && toolbar && toolbar.style.display === 'block') {
        closeMathToolbar();
    }
});
</script>
