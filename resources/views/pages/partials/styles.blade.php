<style>
    .public-page {
        --page-navy: #1d3557;
        --page-teal: #2a9d8f;
        --page-ink: #34445a;
        max-width: 1080px;
        margin: 0 auto;
        padding: 10px 20px 40px;
    }
    .public-page-hero {
        background: linear-gradient(135deg, #17304f 0%, #285b70 62%, #2a9d8f 100%);
        border-radius: 24px;
        color: #fff;
        padding: clamp(38px, 7vw, 72px);
        margin-bottom: 28px;
        box-shadow: 0 22px 50px rgba(29, 53, 87, .18);
    }
    .public-page-hero h1 { color: #fff; font-size: clamp(2rem, 5vw, 3.35rem); margin-bottom: 12px; }
    .public-page-hero p { max-width: 720px; font-size: 1.05rem; line-height: 1.75; margin: 0; opacity: .94; }
    .public-page-hero .eyebrow { display: block; margin-bottom: 12px; color: #b9f3e8; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; font-size: .78rem; }
    .public-page-card {
        background: #fff;
        border: 1px solid #e3e9ef;
        border-radius: 18px;
        padding: clamp(24px, 5vw, 46px);
        box-shadow: 0 10px 32px rgba(29, 53, 87, .07);
    }
    .public-page-card h2 { color: var(--page-navy); font-size: 1.35rem; margin: 30px 0 10px; }
    .public-page-card h2:first-child { margin-top: 0; }
    .public-page-card p, .public-page-card li { color: var(--page-ink); line-height: 1.8; }
    .public-page-card ul { padding-left: 1.25rem; }
    .public-page-note { border-left: 4px solid var(--page-teal); background: #effaf8; padding: 16px 18px; border-radius: 0 10px 10px 0; }
    .public-page-meta { font-size: .9rem; margin-top: 18px !important; opacity: .82; }
    .public-page a { color: #187a70; }
    .public-form label { color: var(--page-navy); font-weight: 700; margin-bottom: 7px; }
    .public-form .form-control, .public-form .form-select { min-height: 48px; border: 1px solid #cfd8e3; border-radius: 10px; }
    .public-form textarea.form-control { min-height: 150px; }
    .public-form .form-control:focus { border-color: var(--page-teal); box-shadow: 0 0 0 .2rem rgba(42,157,143,.14); }
    .public-submit { background: var(--page-teal); border: 0; color: #fff; border-radius: 10px; padding: 13px 25px; font-weight: 800; }
    .public-submit:hover { background: #21877c; color: #fff; }
    .contact-grid { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(240px, .75fr); gap: 24px; }
    .contact-detail { display: flex; gap: 14px; align-items: flex-start; padding: 16px 0; border-bottom: 1px solid #edf0f4; }
    .contact-detail:last-child { border-bottom: 0; }
    .contact-detail i { color: var(--page-teal); font-size: 1.35rem; }
    .contact-detail strong { display: block; color: var(--page-navy); margin-bottom: 3px; }
    .faq-page { max-width: none; padding: 0; margin-top: -40px; }
    .faq-hero {
        position: relative;
        overflow: hidden;
        min-height: 270px;
        color: #fff;
        background:
            radial-gradient(circle at 79% 12%, rgba(26, 182, 171, .32), transparent 30%),
            radial-gradient(circle at 24% 115%, rgba(21, 71, 129, .8), transparent 42%),
            linear-gradient(118deg, #061539 0%, #082552 48%, #078e88 100%);
        border-radius: 0 0 28px 28px;
    }
    .faq-hero::before {
        content: "";
        position: absolute;
        right: -120px;
        bottom: -185px;
        width: 630px;
        height: 420px;
        border: 1px solid rgba(125, 235, 222, .12);
        border-radius: 50%;
        box-shadow:
            0 0 0 18px rgba(125, 235, 222, .035),
            0 0 0 38px rgba(125, 235, 222, .025),
            0 0 0 62px rgba(125, 235, 222, .02);
        transform: rotate(-14deg);
    }
    .faq-hero::after {
        content: "?";
        position: absolute;
        right: 10%;
        top: 48px;
        display: grid;
        place-items: center;
        width: 50px;
        height: 50px;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 50% 50% 50% 18%;
        color: rgba(150, 241, 226, .56);
        background: rgba(255, 255, 255, .035);
        font-size: 1.55rem;
        font-weight: 900;
        transform: rotate(5deg);
    }
    .faq-hero-inner { position: relative; z-index: 1; width: min(960px, calc(100% - 40px)); margin: 0 auto; padding: 30px 0 28px; text-align: center; }
    .faq-hero-eyebrow { display: inline-block; color: #9df0e4; font-size: .62rem; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
    .faq-hero-eyebrow::after { content: ""; display: block; width: 28px; height: 2px; margin: 7px auto 0; border-radius: 3px; background: #42d8c6; }
    .faq-hero h1 { margin: 11px 0 6px; color: #fff; font-size: clamp(1.65rem, 3vw, 2.4rem); line-height: 1.1; letter-spacing: -.035em; text-shadow: 0 3px 16px rgba(0, 0, 0, .16); }
    .faq-hero-copy { margin: 0; color: rgba(255, 255, 255, .88); font-size: clamp(.8rem, 1vw, .9rem); }
    .faq-search { --faq-search-control-height: 48px; position: relative; width: min(760px, 100%); margin: 17px auto 0; text-align: left; }
    .faq-search-icon { position: absolute; left: 18px; top: calc(var(--faq-search-control-height) / 2); color: #fff; font-size: 1.05rem; transform: translateY(-50%); pointer-events: none; }
    .faq-search-input {
        width: 100%;
        height: var(--faq-search-control-height);
        min-height: var(--faq-search-control-height);
        padding: 0 92px 0 46px;
        border: 1px solid rgba(255, 255, 255, .19);
        border-radius: 11px;
        outline: 0;
        color: #fff;
        background: rgba(255, 255, 255, .08);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
        font-size: .84rem;
        backdrop-filter: blur(10px);
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
    }
    .faq-search-input::placeholder { color: rgba(255, 255, 255, .72); }
    .faq-search-input:focus { border-color: rgba(119, 235, 220, .72); background: rgba(255, 255, 255, .12); box-shadow: 0 0 0 4px rgba(66, 216, 198, .12); }
    .faq-search-shortcut { position: absolute; right: 13px; top: calc(var(--faq-search-control-height) / 2); display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 28px; padding: 0 8px; border: 1px solid rgba(255, 255, 255, .2); border-radius: 7px; color: rgba(255,255,255,.88); background: rgba(2, 24, 55, .2); font-size: .65rem; line-height: 1; transform: translateY(-50%); pointer-events: none; white-space: nowrap; }
    .faq-search-clear { position: absolute; right: 68px; top: calc(var(--faq-search-control-height) / 2); display: grid; place-items: center; width: 30px; height: 30px; padding: 0; border: 0; border-radius: 50%; color: #fff !important; background: transparent; text-decoration: none; transform: translateY(-50%); }
    .faq-search-clear:hover { background: rgba(255,255,255,.12); }
    .faq-search-clear[hidden] { display: none; }
    .faq-content { width: min(960px, calc(100% - 40px)); margin: 0 auto; padding: 25px 0 54px; }
    .faq-toolbar { display: flex; flex-wrap: wrap; justify-content: center; gap: 7px; margin-bottom: 19px; }
    .faq-chip { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 0 14px; border: 1px solid #dce3eb; border-radius: 999px; color: #102951 !important; background: #fff; box-shadow: 0 6px 18px rgba(19, 49, 86, .035); text-decoration: none; font-weight: 800; font-size: .72rem; transition: all .2s ease; }
    .faq-chip i { color: #169f90; font-size: .83rem; }
    .faq-chip.active, .faq-chip:hover { color: #fff !important; border-color: #13a38f; background: linear-gradient(135deg, #159b88, #14b697); box-shadow: 0 10px 24px rgba(20, 166, 143, .2); transform: translateY(-1px); }
    .faq-chip.active i, .faq-chip:hover i { color: #fff; }
    .faq-list { display: grid; gap: 11px; transition: opacity .16s ease; }
    .faq-list.is-loading { opacity: .48; pointer-events: none; }
    .faq-search-status { min-height: 18px; margin: 9px 0 0; color: rgba(255,255,255,.72); font-size: .7rem; text-align: center; }
    .faq-item { border: 1px solid #e1e7ee; border-radius: 15px; background: #fff; overflow: hidden; box-shadow: 0 9px 26px rgba(18, 43, 77, .07); transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
    .faq-item:hover { border-color: #cddbdc; box-shadow: 0 13px 32px rgba(18, 43, 77, .1); transform: translateY(-1px); }
    .faq-item summary { cursor: pointer; list-style: none; min-height: 78px; padding: 17px 66px 16px 23px; color: #071f48; font-size: clamp(.86rem, 1.15vw, .98rem); font-weight: 900; line-height: 1.35; position: relative; }
    .faq-item summary::-webkit-details-marker { display: none; }
    .faq-item summary::after { content: '+'; position: absolute; right: 21px; top: 50%; display: grid; place-items: center; width: 32px; height: 32px; border: 1px solid #dce4eb; border-radius: 50%; color: #149c8c; background: #fff; font-size: 1.1rem; font-weight: 500; transform: translateY(-50%); transition: all .2s ease; }
    .faq-item[open] summary::after { content: '−'; color: #fff; border-color: #159f8c; background: #159f8c; }
    .faq-answer { border-top: 1px solid #edf0f3; padding: 15px 23px 18px; color: var(--page-ink); font-size: .82rem; line-height: 1.65; white-space: pre-line; }
    .faq-category { display: block; margin-bottom: 7px; color: #138f83; font-size: .58rem; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; }
    .faq-empty { padding: 64px 20px; border: 1px solid #e1e7ee; border-radius: 16px; background: #fff; box-shadow: 0 9px 26px rgba(18, 43, 77, .06); }
    .legal-page { max-width: none; padding: 0; margin-top: -40px; }
    .legal-hero {
        position: relative;
        overflow: hidden;
        min-height: 245px;
        color: #fff;
        background:
            radial-gradient(circle at 86% 15%, rgba(43, 205, 183, .28), transparent 25%),
            radial-gradient(circle at 52% 120%, rgba(19, 91, 132, .42), transparent 40%),
            linear-gradient(120deg, #061638 0%, #092c58 57%, #087e7b 100%);
        border-radius: 0 0 28px 28px;
    }
    .legal-hero::before {
        content: "";
        position: absolute;
        right: -135px;
        bottom: -220px;
        width: 620px;
        height: 450px;
        border: 1px solid rgba(137, 238, 224, .13);
        border-radius: 50%;
        box-shadow: 0 0 0 24px rgba(137, 238, 224, .035), 0 0 0 52px rgba(137, 238, 224, .025);
        transform: rotate(-12deg);
    }
    .legal-hero-inner { position: relative; z-index: 1; display: flex; flex-direction: column; justify-content: center; width: min(1120px, calc(100% - 40px)); min-height: 245px; margin: 0 auto; padding: 38px 0; }
    .legal-hero-eyebrow { display: inline-flex; align-items: center; gap: 9px; color: #9df0e4; font-size: .62rem; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
    .legal-hero-eyebrow::before { content: ""; width: 28px; height: 2px; border-radius: 3px; background: #42d8c6; }
    .legal-hero h1 { max-width: 560px; margin: 12px 0 7px; color: #fff; font-size: clamp(1.55rem, 2.4vw, 2.15rem); line-height: 1.12; letter-spacing: -.035em; }
    .legal-hero-copy { max-width: 630px; margin: 0; color: rgba(255, 255, 255, .86); font-size: .82rem; line-height: 1.65; }
    .legal-content { width: min(960px, calc(100% - 40px)); margin: 0 auto; padding: 26px 0 64px; }
    .legal-card {
        padding: clamp(24px, 4vw, 42px);
        border: 1px solid #e1e7ee;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 30px rgba(18, 43, 77, .075);
    }
    .legal-card h2 { margin: 26px 0 8px; padding-top: 2px; color: #09234d; font-size: 1.02rem; line-height: 1.35; }
    .legal-card h2:first-of-type { margin-top: 25px; }
    .legal-card p, .legal-card li { color: #42526a; font-size: .84rem; line-height: 1.72; }
    .legal-card p { margin-bottom: 11px; }
    .legal-card a { color: #138f83; font-weight: 700; }
    .legal-document-date { margin: 0 0 14px !important; color: #7b8999 !important; font-size: .68rem !important; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .legal-intro {
        margin: 0 !important;
        padding: 15px 17px;
        border: 1px solid #cfece7;
        border-left: 4px solid #18a491;
        border-radius: 0 10px 10px 0;
        color: #28475e !important;
        background: #f0faf8;
    }
    .contact-page { max-width: none; padding: 0; margin-top: -40px; background: #f4f7fa; }
    .contact-hero {
        position: relative;
        overflow: hidden;
        min-height: 260px;
        color: #fff;
        background:
            radial-gradient(circle at 83% 7%, rgba(46, 205, 184, .3), transparent 27%),
            radial-gradient(circle at 35% 125%, rgba(23, 84, 136, .72), transparent 42%),
            linear-gradient(118deg, #061539 0%, #092b57 54%, #078c86 100%);
        border-radius: 0 0 28px 28px;
    }
    .contact-hero::after {
        content: "";
        position: absolute;
        right: -105px;
        bottom: -205px;
        width: 550px;
        height: 390px;
        border: 1px solid rgba(125, 235, 222, .12);
        border-radius: 50%;
        box-shadow: 0 0 0 22px rgba(125, 235, 222, .035), 0 0 0 48px rgba(125, 235, 222, .023);
        transform: rotate(-12deg);
    }
    .contact-hero-inner {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: end;
        gap: 45px;
        width: min(1040px, calc(100% - 40px));
        margin: 0 auto;
        padding: 48px 0 43px;
    }
    .contact-hero-eyebrow, .contact-section-kicker {
        color: #98eee2;
        font-size: .64rem;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
    }
    .contact-hero-eyebrow::after { content: ""; display: block; width: 29px; height: 2px; margin-top: 8px; border-radius: 3px; background: #42d8c6; }
    .contact-hero h1 { margin: 12px 0 8px; color: #fff; font-size: clamp(1.8rem, 3vw, 2.6rem); line-height: 1.1; letter-spacing: -.035em; }
    .contact-hero p { max-width: 650px; margin: 0; color: rgba(255,255,255,.82); font-size: .86rem; line-height: 1.65; }
    .contact-hero-badges { display: grid; flex: 0 0 215px; gap: 8px; }
    .contact-hero-badges span { display: flex; align-items: center; gap: 8px; padding: 9px 11px; border: 1px solid rgba(255,255,255,.13); border-radius: 9px; color: rgba(255,255,255,.78); background: rgba(255,255,255,.055); font-size: .68rem; backdrop-filter: blur(8px); }
    .contact-hero-badges i { color: #85e6d9; }
    .contact-content { width: min(1040px, calc(100% - 40px)); margin: 0 auto; padding: 28px 0 64px; }
    .contact-success { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; padding: 14px 16px; border: 1px solid #bfe8df; border-radius: 12px; color: #23695f; background: #edfaf7; box-shadow: 0 6px 18px rgba(18, 43, 77, .04); }
    .contact-success > i { color: #1da38f; font-size: 1.25rem; }
    .contact-success strong, .contact-success span { display: block; }
    .contact-success strong { margin-bottom: 2px; color: #0d594f; font-size: .8rem; }
    .contact-success span { font-size: .72rem; }
    .contact-grid { display: grid; grid-template-columns: minmax(0, 1.48fr) minmax(280px, .72fr); gap: 20px; align-items: stretch; }
    .contact-form-card, .contact-panel { border-radius: 17px; box-shadow: 0 12px 32px rgba(18, 43, 77, .07); }
    .contact-form-card { padding: clamp(24px, 4vw, 38px); border: 1px solid #dee6ed; background: #fff; }
    .contact-form-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 22px; margin-bottom: 27px; padding-bottom: 22px; border-bottom: 1px solid #edf1f4; }
    .contact-form-heading .contact-section-kicker { color: #159786; }
    .contact-form-heading h2 { margin: 7px 0 5px; color: #081f48; font-size: 1.3rem; letter-spacing: -.02em; }
    .contact-form-heading p { margin: 0; color: #718095; font-size: .76rem; }
    .contact-form-icon { display: grid; place-items: center; flex: 0 0 45px; width: 45px; height: 45px; border-radius: 12px; color: #159786; background: #e9f8f5; font-size: 1.15rem; }
    .contact-form-card .public-form label { margin-bottom: 6px; color: #183358; font-size: .74rem; font-weight: 800; }
    .contact-form-card .public-form .form-control { min-height: 44px; border-color: #d1dbe4; border-radius: 8px; color: #263b56; background: #fbfcfd; font-size: .8rem; }
    .contact-form-card .public-form textarea.form-control { min-height: 132px; resize: vertical; }
    .contact-submit { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 43px; padding: 0 18px; border: 0; border-radius: 9px; color: #fff; background: linear-gradient(135deg, #159f8d, #168e81); font-size: .76rem; font-weight: 850; box-shadow: 0 8px 20px rgba(21,159,141,.18); transition: transform .2s ease, box-shadow .2s ease; }
    .contact-submit:hover { box-shadow: 0 11px 24px rgba(21,159,141,.25); transform: translateY(-1px); }
    .contact-panel {
        position: relative;
        overflow: hidden;
        padding: 30px 27px;
        border: 1px solid rgba(255,255,255,.05);
        color: rgba(255,255,255,.76);
        background:
            radial-gradient(circle at 100% 0%, rgba(55, 211, 191, .2), transparent 31%),
            linear-gradient(145deg, #071c42 0%, #0a3b5e 100%);
    }
    .contact-panel::after { content: ""; position: absolute; right: -95px; bottom: -115px; width: 230px; height: 230px; border: 1px solid rgba(255,255,255,.08); border-radius: 50%; box-shadow: 0 0 0 19px rgba(255,255,255,.02); }
    .contact-panel .contact-section-kicker { color: #8de8dc; }
    .contact-panel h2 { position: relative; z-index: 1; margin: 8px 0 7px; color: #fff; font-size: 1.25rem; }
    .contact-panel-copy { position: relative; z-index: 1; margin: 0 0 19px; color: rgba(255,255,255,.63); font-size: .72rem; line-height: 1.6; }
    .contact-panel .contact-detail { position: relative; z-index: 1; display: flex; gap: 11px; align-items: flex-start; padding: 13px 0; border-color: rgba(255,255,255,.1); }
    .contact-panel .contact-detail > span:first-child { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 9px; color: #8ee7dc; background: rgba(255,255,255,.08); }
    .contact-panel .contact-detail i { color: inherit; font-size: .9rem; }
    .contact-panel .contact-detail strong { margin-bottom: 2px; color: #fff; font-size: .7rem; }
    .contact-panel .contact-detail div > span, .contact-panel .contact-detail a { color: rgba(255,255,255,.67); font-size: .69rem; line-height: 1.5; text-decoration: none; }
    .contact-panel .contact-detail a:hover { color: #9cecdf; }
    .contact-help { position: relative; z-index: 1; display: flex; gap: 10px; margin-top: 20px; padding: 14px; border: 1px solid rgba(255,255,255,.1); border-radius: 11px; background: rgba(255,255,255,.055); }
    .contact-help > i { color: #8ee7dc; }
    .contact-help strong { display: block; margin-bottom: 3px; color: #fff; font-size: .69rem; }
    .contact-help a { color: #94e9de; font-size: .65rem; font-weight: 700; text-decoration: none; }
    @media (max-width: 760px) {
        .contact-grid { grid-template-columns: 1fr; }
        .public-page:not(.faq-page) { padding-inline: 14px; }
        .faq-hero { min-height: 255px; border-radius: 0 0 22px 22px; }
        .faq-hero-inner { width: min(calc(100% - 28px), 960px); padding: 27px 0 25px; }
        .faq-hero::after { display: none; }
        .faq-search { --faq-search-control-height: 46px; margin-top: 16px; }
        .faq-search-input { padding-left: 43px; padding-right: 46px; font-size: .8rem; }
        .faq-search-icon { left: 16px; font-size: 1rem; }
        .faq-search-shortcut { display: none; }
        .faq-search-clear { right: 14px; }
        .faq-content { width: min(calc(100% - 28px), 960px); padding-top: 21px; }
        .faq-toolbar { gap: 6px; margin-bottom: 17px; }
        .faq-chip { min-height: 34px; padding: 0 11px; font-size: .68rem; }
        .faq-item summary { min-height: 74px; padding: 16px 55px 15px 17px; }
        .faq-item summary::after { right: 14px; width: 30px; height: 30px; }
        .faq-answer { padding: 14px 17px 17px; font-size: .79rem; }
        .legal-hero { min-height: 220px; border-radius: 0 0 22px 22px; }
        .legal-hero-inner { width: min(calc(100% - 28px), 1120px); min-height: 220px; padding: 30px 0; }
        .legal-hero h1 { font-size: 1.5rem; }
        .legal-content { width: min(calc(100% - 28px), 960px); padding-top: 21px; }
        .legal-card { padding: 22px 18px; }
        .legal-card h2 { font-size: .96rem; }
        .legal-card p, .legal-card li { font-size: .8rem; }
        .contact-hero { min-height: 245px; border-radius: 0 0 22px 22px; }
        .contact-hero-inner { display: block; width: min(calc(100% - 28px), 1040px); padding: 36px 0 31px; }
        .contact-hero-badges { display: flex; flex-wrap: wrap; margin-top: 21px; }
        .contact-hero-badges span { padding: 7px 9px; font-size: .62rem; }
        .contact-content { width: min(calc(100% - 28px), 1040px); padding-top: 21px; }
        .contact-form-card, .contact-panel { border-radius: 14px; }
        .contact-form-card { padding: 22px 18px; }
        .contact-form-heading { margin-bottom: 22px; }
        .contact-panel { padding: 25px 21px; }
    }
</style>
