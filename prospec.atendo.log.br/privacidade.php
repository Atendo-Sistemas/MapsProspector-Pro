<?php
require_once __DIR__ . '/config/config.php';
$saasName = 'MapsProspector Pro';
$canonicalUrl = 'https://prospec.atendo.log.br/privacidade.php';
$metaDescription = 'Conheça a Política de Privacidade do MapsProspector e como os dados da conta e da prospecção são tratados.';
if (!headers_sent()) {
    header('X-Robots-Tag: index, follow');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: SAMEORIGIN');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="Política de Privacidade | <?php echo htmlspecialchars($saasName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Política de Privacidade | <?php echo $saasName; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors duration-200">

    <div class="max-w-4xl mx-auto px-6 py-20">
        <!-- Cabeçalho -->
        <div class="mb-12 text-center">
            <a href="/" class="inline-flex items-center gap-2 text-blue-600 font-bold mb-8 hover:underline">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar para o início
            </a>
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">Política de Privacidade</h1>
            <p class="text-slate-500 dark:text-slate-400">Última atualização: <?php echo date('d/m/Y' ); ?></p>
        </div>

        <!-- Conteúdo -->
        <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl border border-slate-200 dark:border-slate-700 p-8 md:p-12 space-y-8 leading-relaxed">
            
            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg flex items-center justify-center text-sm">01</span>
                    Coleta de Informações
                </h2>
                <p>Coletamos informações que você nos fornece diretamente ao criar sua conta, como nome, e-mail, telefone e dados da empresa. Além disso, o sistema processa dados públicos de terceiros (leads) através de APIs de mapeamento para finalidades de prospecção comercial solicitadas pelo usuário.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg flex items-center justify-center text-sm">02</span>
                    Uso dos Dados
                </h2>
                <p>Os dados coletados são utilizados para:</p>
                <ul class="list-disc ml-6 mt-2 space-y-1">
                    <li>Gerenciar sua conta e acesso ao sistema;</li>
                    <li>Processar suas buscas e histórico de prospecção;</li>
                    <li>Enviar comunicações de suporte e atualizações do sistema;</li>
                    <li>Garantir a segurança e prevenir fraudes ou abusos.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg flex items-center justify-center text-sm">03</span>
                    Compartilhamento e Segurança
                </h2>
                <p>Não vendemos seus dados pessoais a terceiros. Seus dados são armazenados em servidores seguros com criptografia e protocolos de proteção. O compartilhamento ocorre apenas com serviços essenciais (como gateways de pagamento ou APIs de busca) estritamente para o funcionamento do serviço.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg flex items-center justify-center text-sm">04</span>
                    Seus Direitos (LGPD)
                </h2>
                <p>Em conformidade com a LGPD, você tem o direito de acessar, corrigir, portar ou solicitar a exclusão de seus dados pessoais a qualquer momento. Para isso, basta entrar em contato através do nosso canal de suporte ou realizar as alterações diretamente no seu painel de perfil.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg flex items-center justify-center text-sm">05</span>
                    Cookies
                </h2>
                <p>Utilizamos cookies essenciais para manter sua sessão ativa e garantir a segurança do login. Você pode gerenciar as preferências de cookies através das configurações do seu navegador, mas isso pode afetar a funcionalidade do sistema.</p>
            </section>

        </div>

        <div class="mt-12 text-center text-slate-500 text-sm italic">
            Para questões relacionadas à privacidade, entre em contato pelo e-mail de suporte.
        </div>
    </div>
</script>
    <!-- Rodapé com branding -->
        <div class="mt-16 text-center text-slate-500 text-sm">
            <p class="mb-2">© <?php echo date('Y'); ?> <strong><?php echo $saasName; ?></strong>. Todos os direitos reservados.</p>
            <p class="text-xs opacity-70 italic">O MapsProspector é uma marca de propriedade da <strong>Atendo Sistemas</strong>.</p>
        </div>
    </div>

    <script>
        if (localStorage.getItem('mapsprospector-theme') === 'dark' || 
            (!('mapsprospector-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script>
        // Sincroniza o tema escuro
        if (localStorage.getItem('mapsprospector-theme') === 'dark' || 
            (!('mapsprospector-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
</body>
</html>
