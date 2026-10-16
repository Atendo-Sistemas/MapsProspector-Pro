<?php
/**
 * Página de perfil comercial NFC — Ivan Jacomassi / Atendo Soluções.
 *
 * Direção visual: cartão editorial técnico. Fundo azul-marinho, acento verde
 * proprietário inspirado na identidade pública da Atendo, tipografia limpa,
 * blocos compactos e chamadas de ação diretas para uma leitura rápida no celular.
 * Esta página é independente do sistema Prospec e não depende de sessão, banco,
 * framework ou bibliotecas externas de JavaScript.
 */

$profile = [
    'name' => 'Ivan Jacomassi',
    'role' => 'CEO · Atendo Soluções',
    'eyebrow' => 'Tecnologia aplicada a negócios reais',
    'summary' => 'Aproximo estratégia, tecnologia e operação para transformar atendimento, vendas e processos em soluções digitais úteis para empresas.',
    'logo' => 'https://atendo.log.br/wp-content/uploads/2026/02/cropped-Atendo_Sistemas_de_Atendimento-removebg-preview-e1770474399344.png',
    'email' => 'contato@atendo.log.br',
    'phone_display' => '(17) 3198-1705',
    'phone_href' => '+551731981705',
    'whatsapp' => 'https://wa.me/551731981705?text=Ol%C3%A1%20Ivan%2C%20conheci%20seu%20perfil%20pelo%20cart%C3%A3o%20NFC%20e%20gostaria%20de%20conversar.',
    'instagram' => 'https://www.instagram.com/atendosistemas/',
];

$links = [
    [
        'label' => 'Falar pelo WhatsApp',
        'href' => $profile['whatsapp'],
        'class' => 'button button-primary',
        'icon' => '↗',
    ],
    [
        'label' => '@atendosistemas',
        'href' => $profile['instagram'],
        'class' => 'button button-secondary',
        'icon' => '◎',
    ],
    [
        'label' => 'Enviar e-mail',
        'href' => 'mailto:' . $profile['email'],
        'class' => 'button button-secondary',
        'icon' => '✉',
    ],
];

$solutions = [
    [
        'number' => '01',
        'title' => 'Atendo Sistema — CRM de Atendimento',
        'text' => 'Gestão completa de clientes e funil de vendas automatizado para organizar o atendimento.',
    ],
    [
        'number' => '02',
        'title' => 'Atendo GO',
        'text' => 'Controle financeiro e gestão comercial simplificados, com emissão automática.',
    ],
    [
        'number' => '03',
        'title' => 'MapsProspect PRO',
        'text' => 'Ferramentas inteligentes para encontrar novos clientes e gerar oportunidades comerciais.',
    ],
    [
        'number' => '04',
        'title' => 'Delivery',
        'text' => 'Sistema de pedidos e logística integrada para apoiar a operação do seu negócio.',
    ],
];

$ecosystem = [
    [
        'title' => 'Atendo Sistemas',
        'description' => 'CRM, atendimento, gestão comercial, prospecção e delivery integrado.',
        'href' => 'https://atendo.log.br/',
        'label' => 'Conhecer soluções',
    ],
    [
        'title' => 'MapsProspect PRO',
        'description' => 'Prospecção inteligente para encontrar novos clientes e oportunidades.',
        'href' => 'https://prospec.atendo.log.br/',
        'label' => 'Acessar plataforma',
    ],
    [
        'title' => 'Classificados Interior',
        'description' => 'Portal de anúncios com categorias de serviços, negócios, veículos e mais.',
        'href' => 'https://classificadosinterior.com.br/site/',
        'label' => 'Visitar portal',
    ],
    [
        'title' => 'Atendo GO',
        'description' => 'Controle financeiro e gestão comercial simplificados, com emissão automática..',
        'href' => 'https://painel.atendo.log.br/',
        'label' => 'Conhecer ambiente',
    ],
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#071821">
    <meta name="description" content="Perfil comercial de Ivan Jacomassi, CEO da Atendo Soluções.">
    <meta property="og:title" content="Ivan Jacomassi · Atendo Soluções">
    <meta property="og:description" content="Tecnologia, aplicações web, atendimento, prospecção e logística de veículos.">
    <meta property="og:image" content="<?= e($profile['logo']) ?>">
    <title><?= e($profile['name']) ?> · <?= e($profile['role']) ?></title>
    <style>
        :root {
            --ink: #071821;
            --ink-soft: #102a34;
            --paper: #f5f8f3;
            --paper-2: #e9f0e9;
            --muted: #6c7d7b;
            --line: rgba(7, 24, 33, .13);
            --green: #b4f000;
            --green-deep: #8fca00;
            --white: #ffffff;
            --shadow: 0 24px 70px rgba(7, 24, 33, .16);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        .page-shell { overflow: hidden; }
        .topline {
            height: 5px;
            background: var(--green);
        }
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
            padding: 22px 0;
        }
        .nav-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--ink);
            text-decoration: none;
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border: 1px solid var(--ink);
            background: var(--green);
            font-size: .78rem;
            font-weight: 900;
        }
        .nav-link {
            color: var(--ink);
            font-size: .77rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-decoration: none;
            text-transform: uppercase;
        }
        .hero {
            position: relative;
            width: min(1120px, calc(100% - 40px));
            margin: 25px auto 0;
            padding: 30px 0 74px;
        }
        .hero::after {
            position: absolute;
            z-index: -1;
            top: 0;
            right: -150px;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: var(--green);
            content: '';
            opacity: .85;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #46605d;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .eyebrow::before {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--green-deep);
            content: '';
        }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr);
            gap: 52px;
            align-items: end;
            margin-top: 28px;
        }
        h1, h2, h3, p { margin-top: 0; }
        h1 {
            max-width: 720px;
            margin-bottom: 22px;
            font-size: clamp(3rem, 8vw, 6.8rem);
            line-height: .9;
            letter-spacing: -.075em;
        }
        .role {
            margin-bottom: 20px;
            font-size: clamp(1rem, 2vw, 1.25rem);
            font-weight: 800;
        }
        .hero-copy {
            max-width: 570px;
            color: #49615f;
            font-size: clamp(1rem, 2vw, 1.22rem);
        }
        .profile-tile {
            position: relative;
            min-height: 305px;
            padding: 24px;
            border: 1px solid rgba(7, 24, 33, .18);
            background: var(--ink);
            box-shadow: var(--shadow);
            transform: rotate(2deg);
        }
        .profile-tile::before {
            position: absolute;
            inset: 10px;
            border: 1px solid rgba(180, 240, 0, .36);
            content: '';
            pointer-events: none;
        }
        .profile-logo {
            display: block;
            width: 88px;
            height: 88px;
            margin-bottom: 70px;
            object-fit: contain;
            background: var(--white);
        }
        .tile-label {
            color: var(--green);
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .tile-name {
            margin: 6px 0 0;
            color: var(--white);
            font-size: 1.45rem;
            font-weight: 900;
            letter-spacing: -.04em;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 52px;
            padding: 13px 17px;
            border: 1px solid var(--ink);
            font-size: .82rem;
            font-weight: 900;
            letter-spacing: .03em;
            text-decoration: none;
            transition: transform .2s ease, background .2s ease;
        }
        .button:hover { transform: translateY(-3px); }
        .button-primary { background: var(--green); }
        .button-primary:hover { background: #c6ff31; }
        .button-secondary { background: transparent; }
        .button-secondary:hover { background: var(--white); }
        .section {
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
            padding: 74px 0;
            border-top: 1px solid var(--line);
        }
        .section-heading {
            display: grid;
            grid-template-columns: .65fr 1fr;
            gap: 30px;
            align-items: start;
            margin-bottom: 42px;
        }
        .section-kicker {
            color: #6a7e79;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        h2 {
            margin-bottom: 0;
            font-size: clamp(2rem, 4vw, 4rem);
            line-height: .95;
            letter-spacing: -.065em;
        }
        .section-intro {
            max-width: 520px;
            margin: 0;
            color: #566a67;
            font-size: 1.05rem;
        }
        .solution-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0;
            border-top: 1px solid var(--line);
        }
        .solution {
            display: grid;
            grid-template-columns: 54px 1fr;
            gap: 18px;
            padding: 25px 18px 28px 0;
            border-bottom: 1px solid var(--line);
        }
        .solution:nth-child(odd) { margin-right: 25px; }
        .solution:nth-child(even) { padding-left: 25px; border-left: 1px solid var(--line); }
        .solution-number { color: var(--green-deep); font-size: .75rem; font-weight: 900; letter-spacing: .12em; }
        .solution h3 { margin-bottom: 8px; font-size: 1.18rem; letter-spacing: -.025em; }
        .solution p { margin-bottom: 0; color: #61716f; font-size: .93rem; }
        .ecosystem {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .ecosystem-card {
            display: flex;
            min-height: 190px;
            flex-direction: column;
            justify-content: space-between;
            padding: 22px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.64);
            text-decoration: none;
            transition: background .2s ease, transform .2s ease;
        }
        .ecosystem-card:hover { background: var(--white); transform: translateY(-4px); }
        .ecosystem-card h3 { margin-bottom: 8px; font-size: 1.1rem; }
        .ecosystem-card p { margin-bottom: 20px; color: #60716e; font-size: .92rem; }
        .card-link { color: #4b6b00; font-size: .76rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .contact {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 36px;
            padding: 42px;
            background: var(--ink);
            color: var(--white);
        }
        .contact h2 { max-width: 520px; color: var(--white); }
        .contact-copy { max-width: 520px; margin: 22px 0 0; color: #b6c9c4; }
        .contact-details { align-self: end; }
        .contact-label { margin-bottom: 8px; color: var(--green); font-size: .7rem; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
        .contact-link { display: block; margin-bottom: 13px; color: var(--white); font-size: 1.08rem; font-weight: 800; text-decoration: none; }
        .contact-link:hover { color: var(--green); }
        .footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
            padding: 27px 0 42px;
            color: #71817e;
            font-size: .78rem;
        }
        .footer p { margin: 0; }
        .footer a { font-weight: 800; text-decoration: none; }
        @media (max-width: 760px) {
            .nav, .hero, .section, .footer { width: min(100% - 28px, 1120px); }
            .nav { padding: 17px 0; }
            .hero { margin-top: 12px; padding-bottom: 58px; }
            .hero::after { right: -245px; top: 90px; width: 320px; height: 320px; }
            .hero-grid, .section-heading, .contact { grid-template-columns: 1fr; }
            .hero-grid { gap: 36px; }
            .profile-tile { max-width: 330px; min-height: 255px; }
            .profile-logo { margin-bottom: 45px; }
            .section { padding: 58px 0; }
            .section-heading { gap: 18px; margin-bottom: 30px; }
            .solution-list, .ecosystem { grid-template-columns: 1fr; }
            .solution:nth-child(odd), .solution:nth-child(even) { margin-right: 0; padding-left: 0; border-left: 0; }
            .solution:last-child { border-bottom: 0; }
            .contact { padding: 28px 24px; gap: 30px; }
            .footer { flex-direction: column; padding-bottom: 30px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="topline"></div>
    <header class="nav">
        <a class="nav-brand" href="#top" aria-label="Perfil de Ivan Jacomassi">
            <span class="brand-mark">AS</span>
            <span>Atendo Soluções</span>
        </a>
        <a class="nav-link" href="#contato">Contato ↘</a>
    </header>

    <main id="top">
        <section class="hero">
            <span class="eyebrow"><?= e($profile['eyebrow']) ?></span>
            <div class="hero-grid">
                <div>
                    <h1><?= e($profile['name']) ?></h1>
                    <p class="role"><?= e($profile['role']) ?></p>
                    <p class="hero-copy"><?= e($profile['summary']) ?></p>
                    <div class="actions" aria-label="Canais de contato">
                        <?php foreach ($links as $link): ?>
                            <a class="<?= e($link['class']) ?>" href="<?= e($link['href']) ?>" target="_blank" rel="noopener noreferrer">
                                <span><?= e($link['label']) ?></span><span aria-hidden="true"><?= e($link['icon']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <aside class="profile-tile" aria-label="Cartão de perfil">
                    <img class="profile-logo" src="<?= e($profile['logo']) ?>" alt="Logo Atendo Sistemas" loading="eager">
                    <div class="tile-label">Perfil comercial</div>
                    <p class="tile-name">Ivan Jacomassi<br>Atendo Soluções</p>
                </aside>
            </div>
        </section>

        <section class="section" id="solucoes">
            <div class="section-heading">
                <span class="section-kicker">O que faço</span>
                <div>
                    <h2>Ideias que viram operação.</h2>
                    <p class="section-intro">Da gestão do atendimento à prospecção e ao delivery, a Atendo conecta tecnologia e execução para resolver desafios comerciais e operacionais.</p>
                </div>
            </div>
            <div class="solution-list">
                <?php foreach ($solutions as $solution): ?>
                    <article class="solution">
                        <span class="solution-number"><?= e($solution['number']) ?></span>
                        <div>
                            <h3><?= e($solution['title']) ?></h3>
                            <p><?= e($solution['text']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" id="ecossistema">
            <div class="section-heading">
                <span class="section-kicker">Ecossistema Atendo</span>
                <div>
                    <h2>Conheça os projetos.</h2>
                    <p class="section-intro">Um conjunto de produtos e iniciativas para apoiar atendimento, prospecção, gestão, anúncios e relacionamento.</p>
                </div>
            </div>
            <div class="ecosystem">
                <?php foreach ($ecosystem as $item): ?>
                    <a class="ecosystem-card" href="<?= e($item['href']) ?>" target="_blank" rel="noopener noreferrer">
                        <div>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e($item['description']) ?></p>
                        </div>
                        <span class="card-link"><?= e($item['label']) ?> ↗</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" id="contato">
            <div class="contact">
                <div>
                    <span class="section-kicker" style="color:#b4f000">Vamos conversar</span>
                    <h2>Seu próximo projeto começa com uma boa conversa.</h2>
                    <p class="contact-copy">Se você busca organizar processos, melhorar o atendimento ou criar uma solução digital para sua operação, fale diretamente comigo.</p>
                </div>
                <div class="contact-details">
                    <div class="contact-label">Contato comercial</div>
                    <a class="contact-link" href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a>
                    <a class="contact-link" href="<?= e($profile['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">WhatsApp · <?= e($profile['phone_display']) ?></a>
                    <a class="contact-link" href="<?= e($profile['instagram']) ?>" target="_blank" rel="noopener noreferrer">Instagram · @atendosistemas</a>
                    <a class="button button-primary" href="<?= e($profile['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Abrir conversa no WhatsApp <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>© <?= date('Y') ?> Atendo Soluções · Ivan Jacomassi</p>
        <p><a href="https://atendo.log.br/" target="_blank" rel="noopener noreferrer">atendo.log.br</a></p>
    </footer>
</div>
</body>
</html>
