<?php
require_once __DIR__ . '/config/config.php';
// Busca o nome do SaaS dinamicamente
$saasName = 'MapsProspector Pro'; // Você pode trocar pelo nome real da sua empresa
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso | <?php echo $saasName; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors duration-200">

    <div class="max-w-4xl mx-auto px-6 py-20">
        <!-- Cabeçalho -->
        <div class="mb-12 text-center">
            <a href="index.php" class="inline-flex items-center gap-2 text-blue-600 font-bold mb-8 hover:underline">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar para o início
            </a>
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">Termos de Uso</h1>
            <p class="text-slate-500 dark:text-slate-400">Última atualização: <?php echo date('d/m/Y' ); ?></p>
        </div>

        <!-- Conteúdo -->
        <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl border border-slate-200 dark:border-slate-700 p-8 md:p-12 space-y-8 leading-relaxed">
            
            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg flex items-center justify-center text-sm">01</span>
                    Aceitação dos Termos
                </h2>
                <p>Ao acessar e utilizar o <strong><?php echo $saasName; ?></strong>, você concorda em cumprir e estar vinculado a estes Termos de Uso. Este sistema é uma ferramenta de auxílio à prospecção e o uso indevido das informações é de total responsabilidade do usuário.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg flex items-center justify-center text-sm">02</span>
                    Uso da Ferramenta e Dados
                </h2>
                <p>O usuário declara estar ciente de que o sistema realiza a coleta de dados públicos disponíveis na internet. É responsabilidade do usuário garantir que o uso desses dados esteja em conformidade com a <strong>LGPD (Lei Geral de Proteção de Dados)</strong>, evitando práticas de SPAM ou assédio comercial.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg flex items-center justify-center text-sm">03</span>
                    Sistema de Créditos e Assinatura
                </h2>
                <p>O acesso às funcionalidades avançadas e extração de dados pode estar sujeito ao consumo de créditos ou planos de assinatura. Créditos não utilizados dentro do período de validade do plano podem expirar conforme a regra da modalidade contratada.</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg flex items-center justify-center text-sm">04</span>
                    Responsabilidade e Garantias
                </h2>
                <p>O <strong><?php echo $saasName; ?></strong> é fornecido "como está". Não garantimos que a ferramenta estará disponível 100% do tempo ou que os dados coletados de terceiros estarão sempre atualizados, visto que dependemos de fontes externas (como Google Maps).</p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg flex items-center justify-center text-sm">05</span>
                    Cancelamento e Reembolso
                </h2>
                <p>O usuário pode cancelar sua assinatura a qualquer momento através do painel de controle. Reembolsos são processados de acordo com o Código de Defesa do Consumidor para produtos digitais dentro do prazo de 7 dias após a primeira compra.</p>
            </section>

        </div>

        <div class="mt-12 text-center text-slate-500 text-sm italic">
            Dúvidas sobre os termos? Entre em contato com nosso suporte.
        </div><!-- Rodapé com branding -->
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
    </div>

    <script>
        // Sincroniza o tema escuro com o sistema
        if (localStorage.getItem('mapsprospector-theme') === 'dark' || 
            (!('mapsprospector-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
</body>
</html>
