// Função para abrir modal de cadastro
function showCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    if (overlay) overlay.classList.remove('hidden');
}

function hideCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    if (overlay) overlay.classList.add('hidden');
}

// Lógica de envio do cadastro
async function handleCadastroSubmit(e) {
    if (e) e.preventDefault();
    
    const company = document.getElementById('reg-company').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;
    const passwordConfirm = document.getElementById('reg-password-confirm').value;
    const errorEl = document.getElementById('reg-error');

    if (password !== passwordConfirm) {
        errorEl.textContent = "As senhas não coincidem.";
        errorEl.classList.remove('hidden');
        return;
    }

    // Chamada para a API
    try {
        const res = await fetch(API_BASE + 'register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ companyName: company, adminEmail: email, adminPassword: password })
        });
        const data = await res.json();
        
        if (data.success) {
            hideCadastro();
            showToast("Empresa cadastrada com sucesso! Faça login.");
        } else {
            errorEl.textContent = data.error || "Erro ao cadastrar.";
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        console.error(err);
    }
}

// Configuração dos Event Listeners (Coloque isso dentro do setupEventListeners)
const linkCadastro = document.getElementById('link-cadastro');
if (linkCadastro) linkCadastro.onclick = showCadastro;

const btnVoltar = document.getElementById('btn-cadastro-voltar');
if (btnVoltar) btnVoltar.onclick = hideCadastro;

const formCadastro = document.getElementById('form-cadastro');
if (formCadastro) formCadastro.onsubmit = handleCadastroSubmit;
