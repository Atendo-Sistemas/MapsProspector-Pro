<?php
require_once __DIR__ . '/config/config.php';
$saasName = 'MapsProspector Pro';
$canonicalUrl = 'https://prospec.atendo.log.br/contato.php';
$metaDescription = 'Entre em contato com a equipe do MapsProspector para dúvidas sobre planos, integrações e suporte técnico.';
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
    <meta property="og:title" content="Contato | <?php echo htmlspecialchars($saasName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Contato | <?php echo $saasName; ?></title>
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
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">Fale Conosco</h1>
            <p class="text-slate-500 dark:text-slate-400">Estamos aqui para ajudar você a escalar suas vendas.</p>
        </div>

        <!-- Conteúdo em Grid -->
        <div class="grid md:grid-cols-2 gap-8">
            
            <!-- Canais de Atendimento -->
            <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl border border-slate-200 dark:border-slate-700 p-8 md:p-10 space-y-8">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Canais Diretos</h2>
                    
                    <div class="space-y-6">
                        <!-- E-mail -->
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-2xl flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">E-mail de Suporte</p>
                                <a href="mailto:contato@atendo.log.br" class="text-lg font-bold text-slate-900 dark:text-white hover:text-blue-600 transition-colors">contato@atendo.log.br</a>
                            </div>
                        </div>

                        <!-- Telefone -->
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-2xl flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Telefone / WhatsApp</p>
                                <a href="tel:1731981705" class="text-lg font-bold text-slate-900 dark:text-white hover:text-blue-600 transition-colors">(17 ) 3198-1705</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 dark:border-slate-700">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">Horário de Atendimento</h2>
                    <p class="text-slate-500 dark:text-slate-400">Segunda a Sexta, das 09h às 18h.</p>
                </div>
            </div>

            <!-- Informações Corporativas -->
            <div class="bg-blue-600 rounded-[2.5rem] shadow-xl p-8 md:p-10 text-white flex flex-col justify-between">
                <div>
                    <h2 class="text-2xl font-black mb-8 italic tracking-tight"><?php echo $saasName; ?></h2>
                    
                    <div class="space-y-8">
                        <div>
                            <p class="text-blue-200 text-xs font-black uppercase tracking-widest mb-2">Localização</p>
                            <p class="text-xl font-bold">São José do Rio Preto, SP</p>
                        </div>

                        <div>
                            <p class="text-blue-200 text-xs font-black uppercase tracking-widest mb-2">Dados Jurídicos</p>
                            <p class="text-xl font-bold">CNPJ 48.992.438/0001-20</p>
                        </div>
                    </div>
                </div>

                <div class="mt-12 p-6 bg-blue-500/30 rounded-3xl border border-blue-400/20">
                    <p class="text-sm font-medium leading-relaxed">
                        Nossa equipe está pronta para tirar suas dúvidas sobre planos, integrações ou suporte técnico.
                    </p>
                </div>
            </div>

        </div>
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
