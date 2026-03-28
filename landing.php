<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MapsProspector - Encontre seus clientes ideais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        blue: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        .animate-float { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .gradient-bg { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-slate-200 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="#" class="text-xl font-black text-blue-600">MapsProspector</a>
                </div>
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Recursos</a>
                    <a href="#how-it-works" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Como funciona</a>
                    <a href="#pricing" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Planos</a>
                    <a href="#faq" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">FAQ</a>
                </div>
                <div class="flex items-center gap-3">
                    <a href="index.php" class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-600 transition">Entrar</a>
                    <a href="#register" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition">Cadastrar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero" class="pt-32 pb-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-4xl mx-auto">
                <span id="hero-badge" class="inline-block px-4 py-1.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full mb-6">🚀 A ferramenta de prospecção B2B</span>
                <h1 id="hero-title" class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 mb-6 leading-tight"></h1>
                <p id="hero-subtitle" class="text-lg md:text-xl text-slate-600 mb-8"></p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a id="hero-cta" href="#register" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white text-lg font-bold rounded-2xl transition shadow-lg shadow-blue-600/25"></a>
                    <a href="#how-it-works" class="px-8 py-4 bg-white border-2 border-slate-200 hover:border-slate-300 text-slate-700 text-lg font-bold rounded-2xl transition">Ver como funciona</a>
                </div>
            </div>
            <div id="hero-content" class="mt-12 max-w-3xl mx-auto text-center text-slate-600"></div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="features-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="features-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div id="features-grid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Features will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="how-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="how-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div class="max-w-4xl mx-auto">
                <div id="how-content" class="bg-white rounded-3xl p-8 md:p-12 shadow-xl">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing / Plans -->
    <section id="pricing" class="py-20 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Nossos Planos</h2>
                <p class="text-lg text-slate-600">Escolha o plano ideal para seu negócio</p>
            </div>
            <div id="plans-grid" class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-6xl mx-auto">
                <!-- Plans will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section id="benefits" class="py-20 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="benefits-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="benefits-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div id="benefits-grid" class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Benefits will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="py-20 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="testimonials-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="testimonials-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div id="testimonials-grid" class="grid md:grid-cols-3 gap-8">
                <!-- Testimonials will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 px-4 bg-slate-50">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-16">
                <h2 id="faq-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="faq-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div id="faq-list" class="space-y-4">
                <!-- FAQ will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section id="register" class="py-20 px-4 gradient-bg">
        <div class="max-w-4xl mx-auto text-center">
            <h2 id="cta-title" class="text-3xl md:text-4xl font-black text-white mb-4"></h2>
            <p id="cta-subtitle" class="text-lg text-blue-100 mb-8"></p>
            <div id="cta-content" class="text-blue-50 mb-8"></div>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a id="cta-button" href="index.php?register=1" class="px-8 py-4 bg-white text-blue-600 text-lg font-bold rounded-2xl transition shadow-lg hover:shadow-xl"></a>
                <a id="cta-secondary" href="#contact" class="px-8 py-4 bg-blue-500 hover:bg-blue-400 text-white text-lg font-bold rounded-2xl transition">Falar com Consultor</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-4 bg-slate-900 text-slate-400">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-2xl font-black text-white">MapsProspector</div>
                <div id="footer-content" class="text-sm"></div>
                <div class="flex gap-6">
                    <a href="#" class="hover:text-white transition">Termos</a>
                    <a href="#" class="hover:text-white transition">Privacidade</a>
                    <a href="#" class="hover:text-white transition">Contato</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    const API_BASE = 'api/';
    
    // Icon SVGs
    const icons = {
        search: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>',
        map: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        chart: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
        zap: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
        shield: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>',
        users: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
        clock: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        'check-circle': '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        'trending-up': '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>',
        headphones: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 18v-6a9 9 0 0118 0v6M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z" /></svg>'
    };

    function renderSection(data) {
        if (!data || !data.sectionKey) return '';
        
        const section = data;
        
        switch(section.sectionKey) {
            case 'hero':
                document.getElementById('hero-title').textContent = section.sectionTitle || '';
                document.getElementById('hero-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('hero-content').innerHTML = section.sectionContent || '';
                if (section.extraData && section.extraData.cta_text) {
                    document.getElementById('hero-cta').textContent = section.extraData.cta_text;
                }
                break;
                
            case 'features':
                document.getElementById('features-title').textContent = section.sectionTitle || '';
                document.getElementById('features-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('features-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-slate-50 rounded-2xl p-6 hover:shadow-lg transition">
                            <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 mb-4">${icons[item.icon] || icons.search}</div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">${item.title}</h3>
                            <p class="text-slate-600 text-sm">${item.description}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'how_it_works':
                document.getElementById('how-title').textContent = section.sectionTitle || '';
                document.getElementById('how-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('how-content').innerHTML = section.sectionContent || '';
                break;
                
            case 'benefits':
                document.getElementById('benefits-title').textContent = section.sectionTitle || '';
                document.getElementById('benefits-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('benefits-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-white rounded-2xl p-6 shadow-sm">
                            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 mb-4">${icons[item.icon] || icons['check-circle']}</div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">${item.title}</h3>
                            <p class="text-slate-600 text-sm">${item.description}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'testimonials':
                document.getElementById('testimonials-title').textContent = section.sectionTitle || '';
                document.getElementById('testimonials-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('testimonials-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-slate-50 rounded-2xl p-6">
                            <p class="text-slate-700 mb-4">"${item.text}"</p>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">${item.name.charAt(0)}</div>
                                <div>
                                    <p class="font-bold text-slate-900 text-sm">${item.name}</p>
                                    <p class="text-slate-500 text-xs">${item.company}</p>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
                break;
                
            case 'faq':
                document.getElementById('faq-title').textContent = section.sectionTitle || '';
                document.getElementById('faq-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('faq-list').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-white rounded-xl p-6 shadow-sm">
                            <h3 class="font-bold text-slate-900 mb-2">${item.question}</h3>
                            <p class="text-slate-600 text-sm">${item.answer}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'cta':
                document.getElementById('cta-title').textContent = section.sectionTitle || '';
                document.getElementById('cta-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('cta-content').innerHTML = section.sectionContent || '';
                if (section.extraData && section.extraData.cta_text) {
                    document.getElementById('cta-button').textContent = section.extraData.cta_text;
                }
                if (section.extraData && section.extraData.secondary_cta_text) {
                    document.getElementById('cta-secondary').textContent = section.extraData.secondary_cta_text;
                }
                break;
                
            case 'footer':
                document.getElementById('footer-content').innerHTML = section.sectionContent || '';
                break;
        }
    }

    function renderPlans(plans) {
        const plansGrid = document.getElementById('plans-grid');
        if (!plans || plans.length === 0) {
            plansGrid.innerHTML = '<p class="text-center col-span-full text-slate-500">Nenhum plano disponível no momento.</p>';
            return;
        }
        
        plansGrid.innerHTML = plans.map(plan => {
            const isFree = plan.priceMonthly === 0 || plan.priceMonthly === '0.00' || !plan.priceMonthly;
            const price = isFree ? 'Grátis' : 'R$ ' + parseFloat(plan.priceMonthly).toFixed(2).replace('.', ',');
            
            return `
                <div class="bg-slate-50 rounded-2xl p-6 ${isFree ? 'ring-2 ring-blue-500' : ''}">
                    <h3 class="text-xl font-black text-slate-900 mb-2">${plan.name}</h3>
                    <div class="mb-4">
                        <span class="text-3xl font-black text-slate-900">${price}</span>
                        ${!isFree ? '<span class="text-slate-500">/mês</span>' : ''}
                    </div>
                    <p class="text-slate-600 text-sm mb-6">${plan.tokenLimit || 0} buscas por mês</p>
                    <a href="index.php?register=1&plan=${plan.slug}" class="block w-full py-3 text-center ${isFree ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-900 hover:bg-slate-800'} text-white font-bold rounded-xl transition">Assinar</a>
                </div>
            `;
        }).join('');
    }

    // Load content
    async function loadContent() {
        try {
            const res = await fetch(API_BASE + 'landing-page-public.php');
            const data = await res.json();
            
            if (data.success && data.sections) {
                data.sections.forEach(renderSection);
            }
        } catch (err) {
            console.error('Error loading landing page content:', err);
        }
    }

    async function loadPlans() {
        try {
            const res = await fetch(API_BASE + 'plans-public.php');
            const data = await res.json();
            
            if (data.success && data.data && data.data.items) {
                renderPlans(data.data.items);
            }
        } catch (err) {
            console.error('Error loading plans:', err);
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        loadContent();
        loadPlans();
    });
    </script>
</body>
</html>
