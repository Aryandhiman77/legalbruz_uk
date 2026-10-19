@extends('layouts.app')

@section('content')
    <style>
        body:has(.err-service-page) #app > main{padding:0!important}
        body:has(.err-service-page){background:#fff}
        .err-service-page{width:100%;max-width:none;margin:0;padding:0;color:#14294b;font-size:13px}
        .err-service-frame{background:#fff;border:0;border-radius:0;box-shadow:none;overflow:hidden}
        .err-hero{position:relative;overflow:hidden;padding:18px 36px!important;background:radial-gradient(circle at 75% 20%,rgba(47,117,194,.28),transparent 34%),linear-gradient(135deg,#082453 0%,#0d3473 58%,#123f88 100%);color:#fff}
        .err-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,36,83,.98) 0%,rgba(8,36,83,.88) 46%,rgba(8,36,83,.34) 100%);z-index:0}
        .err-hero:after{content:"";position:absolute;right:70px;top:-28px;width:510px;height:250px;border:1px solid rgba(255,255,255,.1);background:linear-gradient(135deg,transparent 0 22%,rgba(255,255,255,.05) 22% 23%,transparent 23% 40%,rgba(255,255,255,.04) 40% 41%,transparent 41%);opacity:.5;transform:skewX(-12deg);z-index:0}
        .err-hero-content{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,650px) minmax(520px,1fr);gap:28px;align-items:end}
        .err-hero-copy{min-width:0}
        .err-service-badge{display:inline-flex;align-items:center;gap:7px;min-height:29px;border-radius:8px;background:rgba(42,157,143,.22);border:1px solid rgba(42,157,143,.28);padding:0 11px;color:#b7eee6;font-weight:850;font-size:.76rem}
        .err-service-badge i{font-size:.84rem;color:#45d4c4}
        .err-hero h1{margin:13px 0 6px;color:#fff!important;font-size:1.82rem;line-height:1.08;font-weight:900;letter-spacing:-.03em}
        .err-hero p{margin:0;max-width:575px;color:rgba(255,255,255,.88);font-size:.88rem;line-height:1.35;font-weight:650}
        .err-hero-stats{display:flex;justify-content:flex-end;gap:26px;align-items:center;margin:0 0 2px}
        .err-stat{display:flex;align-items:center;gap:10px;min-width:150px}
        .err-stat-icon{position:relative;width:43px;height:43px;border-radius:50%;background:linear-gradient(135deg,rgba(42,157,143,.46),rgba(255,255,255,.12));box-shadow:inset 0 0 0 1px rgba(255,255,255,.1);color:#eaf7ff;font-size:2rem}
        .err-stat strong{display:block;color:#fff;font-size:.82rem;line-height:1.15;font-weight:900}
        .err-stat span{display:block;margin-top:2px;color:rgba(255,255,255,.78);font-size:.72rem;font-weight:600}
        .err-main{padding:24px 30px 26px!important}
        .err-content-grid{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:16px;align-items:start}
        .err-panel,.err-card,.err-timeline,.err-alert{background:#fff;border:1px solid #e0e9f5;border-radius:11px;box-shadow:0 6px 18px rgba(15,36,68,.035);padding:0!important}
        .err-panel-body{padding:15px 18px 14px}
        .err-panel-title{display:grid;grid-template-columns:56px 1fr;gap:12px;align-items:center;margin-bottom:14px}
        .err-title-icon{width:54px;height:54px;border-radius:50%;background:#eaf2ff;color:#153b81;font-size:1.7rem}
        .err-panel h2,.err-side h2{margin:0;color:#112b55!important;font-size:1.08rem;line-height:1.16;font-weight:900;letter-spacing:-.015em}
        .err-panel-title p,.err-side-intro,.err-info p,.err-security p,.err-feature p,.err-step p,.err-alert p{margin:5px 0 0;color:#52627a;font-size:.78rem;line-height:1.35;font-weight:600}
        .err-feature-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
        .err-feature{position:relative;min-height:118px;border:1px solid #e3ebf6;border-radius:10px;background:linear-gradient(180deg,#fff,#fbfdff);padding:11px 12px 10px;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
        .err-feature:hover{transform:translateY(-2px);border-color:#bdd7f5;box-shadow:0 12px 24px rgba(19,64,130,.08)}
        .err-feature-icon{width:38px;height:38px;border-radius:50%;margin-bottom:8px;background:#eaf2ff;color:#246bda;font-size:1.28rem}
        .err-feature:nth-child(2n) .err-feature-icon,.err-info-icon.teal{background:#dff6ef;color:#2a9d8f}
        .err-feature h3{margin:0;color:#0f2c5f;font-size:.82rem;line-height:1.16;font-weight:900}
        .err-feature p{padding-right:22px}
        .err-feature-arrow{position:absolute;right:10px;bottom:10px;width:22px;height:22px;border-radius:50%;background:#eef4fb;color:#5d77a0;font-size:.86rem}
        .err-side{display:grid;gap:10px}
        .err-card{padding:18px 22px!important;background:linear-gradient(145deg,#fff 0%,#f8fffd 100%)}
        .err-side h2:after{content:"";display:block;width:30px;height:3px;border-radius:999px;background:#1fb09f;margin-top:9px}
        .err-side-intro{max-width:320px;margin-top:12px}
        .err-actions{display:grid;gap:10px;margin:18px 0 15px}
        .err-btn{width:100%;min-height:44px;border:0;border-radius:7px;background:#149d91;color:#fff!important;text-decoration:none;font-size:.88rem;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 8px 18px rgba(20,157,145,.16)}
        .err-btn:hover{background:#0e897e;color:#fff!important}
        .err-btn.secondary{background:#062567;box-shadow:0 8px 18px rgba(6,37,103,.18)}
        .err-btn.secondary:hover{background:#041d53}
        .err-card-divider{height:1px;background:#dce6f2;margin:0 0 14px}
        .err-info-title{margin:0 0 11px;color:#0f2c5f;font-size:.82rem;font-weight:900}
        .err-info-list{display:grid;gap:10px}
        .err-info{display:grid;grid-template-columns:36px 1fr;gap:10px;align-items:start}
        .err-info-icon{width:34px;height:34px;border-radius:8px;background:#e4f5f2;color:#1e9488;font-size:1.14rem}
        .err-info strong,.err-security strong{display:block;color:#152b50;font-size:.76rem;line-height:1.2;font-weight:900}
        .err-info p{font-size:.72rem;margin-top:2px}
        .err-security{display:grid;grid-template-columns:46px 1fr;gap:10px;align-items:center;padding:12px 14px;border:1px solid #e0e9f5;border-radius:11px;background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:0 6px 18px rgba(15,36,68,.035)}
        .err-security-icon{width:42px;height:42px;border-radius:50%;background:#edf3fb;color:#123775;font-size:1.25rem}
        .err-security p{font-size:.73rem;margin-top:3px}
        .err-timeline{margin-top:10px;padding:12px 18px 13px!important}
        .err-timeline h3{margin:0 0 9px;color:#0f2c5f;font-size:.88rem;font-weight:900}
        .err-steps{position:relative;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
        .err-steps:before{content:"";position:absolute;left:7%;right:7%;top:32px;border-top:2px dashed #9bbbe9;z-index:0}
        .err-step{position:relative;z-index:1;text-align:center}
        .err-step-icon{width:52px;height:52px;border-radius:50%;margin:0 auto 6px;background:#eaf2ff;border:1px solid #cfe0fa;color:#246bda;font-size:1.55rem}
        .err-step:nth-child(2n) .err-step-icon{background:#dff6ef;border-color:#c9eee5;color:#2a9d8f}
        .err-step-num{position:absolute;left:50%;top:43px;transform:translateX(-50%);width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#135fba;color:#fff;font-size:.64rem;font-weight:900}
        .err-step:nth-child(2n) .err-step-num{background:#138679}
        .err-step strong{display:block;margin-top:12px;color:#0f2c5f;font-size:.72rem;line-height:1.15;font-weight:900}
        .err-step p{font-size:.66rem;line-height:1.28;margin-top:3px}
        .err-alert-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.14fr);gap:12px;margin-top:12px}
        .err-alert{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:center;padding:13px 16px!important}
        .err-alert.warning{background:#fffaf0;border-color:#f6dda7}
        .err-alert.info{background:#f5faff;border-color:#d8e8fb}
        .err-alert-icon{width:38px;height:38px;border-radius:50%;color:#fff;font-size:1.22rem;background:#f59e0b}
        .err-alert.info .err-alert-icon{background:#1559b7}
        .err-alert strong{display:block;color:#56380a;font-size:.82rem;font-weight:900}
        .err-alert.info strong{color:#0f3b7c}
        .err-alert p{font-size:.72rem;color:#4c5c72;margin-top:3px}
        .err-stat-icon,.err-title-icon,.err-feature-icon,.err-feature-arrow,.err-info-icon,.err-security-icon,.err-step-icon,.err-alert-icon{display:flex;align-items:center;justify-content:center;line-height:0;text-align:center;flex:0 0 auto}
        .err-stat-icon i,.err-title-icon i,.err-feature-icon i,.err-feature-arrow i,.err-info-icon i,.err-security-icon i,.err-step-icon i,.err-alert-icon i,.err-service-badge i,.err-btn i{display:block;width:1em;height:1em;line-height:1;text-align:center;margin:0;transform:none}
        .err-stat-icon i:before,.err-title-icon i:before,.err-feature-icon i:before,.err-feature-arrow i:before,.err-info-icon i:before,.err-security-icon i:before,.err-step-icon i:before,.err-alert-icon i:before,.err-service-badge i:before,.err-btn i:before{display:block;line-height:1}
        .err-stat-icon i{position:absolute!important;left:50%!important;top:50%!important;display:block!important;margin:0!important;width:auto!important;height:auto!important;line-height:1!important;transform:translate(-50%,-50%)!important}
        .err-stat-icon i:before{font-size:26px!important;line-height:1!important}
        @media(max-width:1280px){.err-hero-content{grid-template-columns:minmax(0,620px) 1fr}.err-hero-stats{gap:16px}.err-stat{min-width:130px}.err-stat strong{font-size:.76rem}.err-stat span{font-size:.68rem}}
        @media(max-width:1100px){.err-hero-content{grid-template-columns:1fr;gap:14px;align-items:start}.err-hero-stats{justify-content:flex-start;margin-top:0}.err-content-grid{grid-template-columns:1fr}.err-side{grid-template-columns:1fr 1fr}.err-security{align-self:start}.err-feature-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:820px){.err-hero{padding:18px 16px!important}.err-hero h1{font-size:1.55rem}.err-hero p{font-size:.8rem}.err-hero-stats{gap:12px;flex-wrap:wrap;margin-top:0}.err-stat-icon{width:38px;height:38px;font-size:1.75rem}.err-stat-icon i:before{font-size:23px!important}.err-stat strong{font-size:.76rem}.err-stat span{font-size:.68rem}.err-main{padding:16px 14px 18px!important}.err-panel-title{grid-template-columns:48px 1fr;gap:10px}.err-title-icon{width:46px;height:46px;font-size:1.35rem}.err-side{grid-template-columns:1fr}.err-steps{grid-template-columns:1fr}.err-steps:before{display:none}.err-step{display:grid;grid-template-columns:46px 1fr;gap:10px;text-align:left;align-items:center}.err-step-icon{width:46px;height:46px;margin:0;font-size:1.3rem}.err-step-num{left:38px;top:35px}.err-step strong{margin-top:0}.err-alert-grid{grid-template-columns:1fr}}
        @media(max-width:560px){.err-hero h1{font-size:1.36rem}.err-hero p{font-size:.76rem}.err-service-badge{min-height:26px;font-size:.7rem}.err-hero-stats{display:grid}.err-feature-grid{grid-template-columns:1fr}.err-panel-body,.err-card{padding:14px!important}.err-btn{min-height:42px;font-size:.82rem}.err-alert,.err-security{grid-template-columns:1fr}.err-main{padding:14px 10px 16px!important}.err-panel-title{grid-template-columns:1fr}.err-feature{min-height:auto}}
    </style>

    <div class="err-service-page">
        <div class="err-service-frame">
            <section class="err-hero">
                <div class="err-hero-content">
                    <div class="err-hero-copy">
                        <span class="err-service-badge"><i class="bi bi-shield-check"></i> Trademark Service</span>
                        <h1>UK Trademark Objection Reply</h1>
                        <p>We help you analyse, draft, and file a strong legal reply to a UKIPO Examination Report before the deadline.</p>
                    </div>

                    <div class="err-hero-stats">
                        <div class="err-stat">
                            <span class="err-stat-icon"><i class="bi bi-calendar3"></i></span>
                            <div><strong>2-Month Deadline</strong><span>Check your report</span></div>
                        </div>
                        <div class="err-stat">
                            <span class="err-stat-icon"><i class="bi bi-shield-check"></i></span>
                            <div><strong>Legal Review</strong><span>Expert IP Lawyers</span></div>
                        </div>
                        <div class="err-stat">
                            <span class="err-stat-icon"><i class="bi bi-bank"></i></span>
                            <div><strong>UKIPO Filing</strong><span>With the UK office</span></div>
                        </div>
                    </div>
                </div>
            </section>

            <main class="err-main">
                <div class="err-content-grid">
                    <div>
                        <section class="err-panel">
                            <div class="err-panel-body">
                                <div class="err-panel-title">
                                    <span class="err-title-icon"><i class="bi bi-file-earmark-check"></i></span>
                                    <div>
                                        <h2>Trademark Objection Reply</h2>
                                        <p>Comprehensive support to address examiner objections and file a well-drafted reply with the UK Intellectual Property Office (UKIPO).</p>
                                    </div>
                                </div>

                                <div class="err-feature-grid">
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-search"></i></span>
                                        <h3>Examination Report review</h3>
                                        <p>Thorough review of the Examination Report and raised objections.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-columns-gap"></i></span>
                                        <h3>Legal objection analysis</h3>
                                        <p>In-depth legal analysis and strategy to address each objection.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-pencil"></i></span>
                                        <h3>Drafting reply</h3>
                                        <p>Draft a strong, clear, and legally sound reply to the examiner.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-folder2-open"></i></span>
                                        <h3>Evidence/document review</h3>
                                        <p>Review and organize supporting evidence and documents effectively.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-send"></i></span>
                                        <h3>Filing reply with UKIPO</h3>
                                        <p>File the reply with UKIPO within the deadline stated in the Examination Report.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                    <article class="err-feature">
                                        <span class="err-feature-icon"><i class="bi bi-clock-history"></i></span>
                                        <h3>Status tracking after filing</h3>
                                        <p>Track status updates and receive timely notifications after filing.</p>
                                        <span class="err-feature-arrow"><i class="bi bi-arrow-right"></i></span>
                                    </article>
                                </div>
                            </div>
                        </section>

                        <section class="err-timeline">
                            <h3>How it works</h3>
                            <div class="err-steps">
                                <div class="err-step">
                                    <span class="err-step-icon"><i class="bi bi-cloud-upload"></i></span>
                                    <span class="err-step-num">1</span>
                                    <div><strong>Upload Report</strong><p>Upload your Examination Report and case details.</p></div>
                                </div>
                                <div class="err-step">
                                    <span class="err-step-icon"><i class="bi bi-shield-check"></i></span>
                                    <span class="err-step-num">2</span>
                                    <div><strong>Legal Review</strong><p>Our experts review and analyze the objections.</p></div>
                                </div>
                                <div class="err-step">
                                    <span class="err-step-icon"><i class="bi bi-file-earmark-check"></i></span>
                                    <span class="err-step-num">3</span>
                                    <div><strong>Draft Approval</strong><p>Review and approve the draft reply.</p></div>
                                </div>
                                <div class="err-step">
                                    <span class="err-step-icon"><i class="bi bi-bank"></i></span>
                                    <span class="err-step-num">4</span>
                                    <div><strong>Filing</strong><p>We file the reply with UKIPO.</p></div>
                                </div>
                                <div class="err-step">
                                    <span class="err-step-icon"><i class="bi bi-graph-up-arrow"></i></span>
                                    <span class="err-step-num">5</span>
                                    <div><strong>Track Status</strong><p>We track updates and keep you informed.</p></div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside class="err-side">
                        <section class="err-card">
                            <h2>Start Your Reply</h2>
                            <p class="err-side-intro">Begin your Examination Report reply process in a few simple steps.</p>
                            <div class="err-actions">
                                <a class="err-btn" href="{{ auth()->check() ? route('examination-reply.create') : route('login') }}">
                                    <i class="bi bi-arrow-right-circle"></i> Start Objection Reply
                                </a>
                            </div>
                            <div class="err-card-divider"></div>
                            <h3 class="err-info-title">Information You'll Need</h3>
                            <div class="err-info-list">
                                <div class="err-info">
                                    <span class="err-info-icon"><i class="bi bi-file-earmark-text"></i></span>
                                    <div><strong>Start with Examination Report PDF</strong><p>Upload the official Examination Report.</p></div>
                                </div>
                                <div class="err-info">
                                    <span class="err-info-icon teal"><i class="bi bi-hash"></i></span>
                                    <div><strong>Application number and class</strong><p>Enter your trademark application details.</p></div>
                                </div>
                                <div class="err-info">
                                    <span class="err-info-icon"><i class="bi bi-calendar3"></i></span>
                                    <div><strong>Filing date</strong><p>Original filing date of the application.</p></div>
                                </div>
                                <div class="err-info">
                                    <span class="err-info-icon teal"><i class="bi bi-clock"></i></span>
                                    <div><strong>Examination Report date</strong><p>Date shown on the UKIPO Examination Report.</p></div>
                                </div>
                            </div>
                        </section>

                        <div class="err-security">
                            <span class="err-security-icon"><i class="bi bi-lock"></i></span>
                            <div>
                                <strong>Your data is secure with us</strong>
                                <p>Confidential, encrypted, and used only for your case.</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="err-alert-grid">
                    <section class="err-alert warning">
                        <span class="err-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        <div>
                            <strong>Deadline Alert</strong>
                            <p>UKIPO generally allows two months to resolve objections. Always follow the response deadline stated in your Examination Report, as missing it may result in refusal of the application.</p>
                        </div>
                    </section>
                    <section class="err-alert info">
                        <span class="err-alert-icon"><i class="bi bi-info-lg"></i></span>
                        <div>
                            <strong>Important Clarification</strong>
                            <p>A trade mark objection is not an opposition. An objection is raised by a UKIPO examiner during examination; an opposition may be filed by a third party after the application is published in the online trade marks journal.</p>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
@endsection
