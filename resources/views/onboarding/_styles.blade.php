{{-- Shared onboarding / quest UI styles (Phase 2, no build step needed) --}}
<style>
    .ob-journey {
        background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 55%, #7c3aed 100%);
        border-radius: 16px; padding: 16px 18px; color: #fff;
        margin-bottom: 16px; position: relative; overflow: hidden;
        border: 1px solid rgba(255,255,255,.14);
        box-shadow: 0 12px 28px -12px rgba(67,56,202,.55);
    }
    .ob-journey::before {
        content: ''; position: absolute; top: -46px; inset-inline-end: -30px;
        width: 170px; height: 170px; border-radius: 50%;
        background: rgba(255,255,255,.12);
    }
    .ob-journey::after {
        content: ''; position: absolute; bottom: -60px; inset-inline-end: 90px;
        width: 120px; height: 120px; border-radius: 50%;
        background: rgba(255,255,255,.07);
    }
    .ob-journey-head { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .ob-journey-ico {
        width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center;
        background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.25);
        font-size: 1.2rem; flex: none;
    }
    .ob-journey-title { font-weight: 800; font-size: 1.02rem; margin: 0; letter-spacing: -.01em; }
    .ob-journey-sub { font-size: .8rem; opacity: .85; margin: 2px 0 0; }
    .ob-journey-bar { position: relative; z-index: 1; height: 8px; border-radius: 99px; background: rgba(255,255,255,.18); margin-top: 12px; overflow: hidden; }
    .ob-journey-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #a5b4fc, #f0abfc); transition: width .5s ease; }
    .ob-modules { position: relative; z-index: 1; display: flex; gap: 8px; margin-top: 12px; overflow-x: auto; padding-bottom: 2px; scrollbar-width: thin; }
    .ob-mod {
        flex: none; display: flex; align-items: center; gap: 8px; text-decoration: none;
        background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
        color: #fff; border-radius: 12px; padding: 8px 12px; font-size: .78rem; font-weight: 700;
        opacity: .55; filter: grayscale(.5); transition: all .18s ease;
    }
    .ob-mod:hover { opacity: .9; color: #fff; transform: translateY(-1px); }
    .ob-mod.is-active { opacity: 1; filter: none; background: #fff; color: #1e1b4b; border-color: #fff; box-shadow: 0 10px 22px -10px rgba(0,0,0,.5); }
    .ob-mod.is-progress { opacity: .9; filter: none; }
    .ob-mod.is-done { opacity: 1; filter: none; background: rgba(34,197,94,.18); border-color: rgba(134,239,172,.5); }
    .ob-mod .ob-pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,.6); animation: ob-pulse 2s ease-out infinite; flex: none; }
    @keyframes ob-pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.55);} 70% { box-shadow: 0 0 0 7px rgba(34,197,94,0);} 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0);} }
    .ob-mod small { font-weight: 600; opacity: .8; font-variant-numeric: tabular-nums; }

    .ob-quest {
        background: #fff; border: 1.5px solid #e0e7ff; border-radius: 14px;
        padding: 12px 14px; margin-bottom: 14px;
        box-shadow: 0 6px 18px -10px rgba(79,70,229,.35);
    }
    .ob-quest-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ob-quest-badge {
        display: inline-flex; align-items: center; gap: 6px; font-size: .7rem; font-weight: 800;
        color: #4338ca; background: #eef2ff; border: 1px solid #e0e7ff;
        border-radius: 999px; padding: 3px 10px;
    }
    .ob-quest-title { font-weight: 800; font-size: .9rem; color: #0f172a; margin: 0; }
    .ob-quest-steps { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .ob-step {
        flex: 1 1 180px; display: flex; gap: 9px; align-items: flex-start; text-align: start;
        border: 1px solid #e5e7eb; border-radius: 11px; padding: 9px 11px; background: #f8fafc;
        font-family: inherit; cursor: pointer; transition: all .15s ease; position: relative;
    }
    .ob-step:hover { border-color: #c7d2fe; background: #f5f7ff; transform: translateY(-1px); }
    .ob-step.is-done { background: #f0fdf4; border-color: #bbf7d0; }
    .ob-step.is-current { border-color: #818cf8; background: linear-gradient(135deg, #eef2ff, #faf5ff); box-shadow: 0 0 0 3px rgba(129,140,248,.18); }
    .ob-step-check {
        width: 22px; height: 22px; border-radius: 50%; flex: none; display: grid; place-items: center;
        border: 2px solid #cbd5e1; color: transparent; font-size: .75rem; background: #fff;
    }
    .ob-step.is-done .ob-step-check { background: #16a34a; border-color: #16a34a; color: #fff; }
    .ob-step b { display: block; font-size: .78rem; color: #0f172a; }
    .ob-step small { display: block; font-size: .7rem; color: #64748b; line-height: 1.5; }
    .ob-spot {
        outline: 2px solid #818cf8 !important; outline-offset: 3px;
        box-shadow: 0 0 0 6px rgba(129,140,248,.22), 0 12px 30px -12px rgba(79,70,229,.6) !important;
        animation: ob-spot 1.6s ease-in-out infinite;
    }
    @keyframes ob-spot { 0%,100% { box-shadow: 0 0 0 4px rgba(129,140,248,.18);} 50% { box-shadow: 0 0 0 9px rgba(129,140,248,.08);} }

    .ob-track-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
    @media (max-width: 640px) { .ob-track-grid { grid-template-columns: 1fr; } }
    .ob-track {
        display: flex; gap: 11px; align-items: flex-start; text-align: start; cursor: pointer;
        border: 2px solid #e5e7eb; border-radius: 14px; padding: 12px; background: #fff;
        font-family: inherit; transition: all .15s ease; position: relative;
    }
    .ob-track:hover { border-color: #c7d2fe; transform: translateY(-1px); }
    .ob-track.is-picked { border-color: var(--tc, #6366f1); background: linear-gradient(135deg, #f8faff, #fff); box-shadow: 0 10px 24px -14px rgba(79,70,229,.6); }
    .ob-track-ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 1.1rem; flex: none; }
    .ob-track b { display: block; font-size: .85rem; color: #0f172a; }
    .ob-track small { display: block; font-size: .72rem; color: #64748b; line-height: 1.55; }
    .ob-track-order {
        position: absolute; top: -9px; inset-inline-end: -7px; min-width: 24px; height: 24px;
        border-radius: 999px; display: grid; place-items: center; font-size: .72rem; font-weight: 800;
        background: linear-gradient(135deg, #4f46e5, #8b5cf6); color: #fff;
        box-shadow: 0 6px 14px -6px rgba(79,70,229,.8);
    }
    .ob-seed-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
    .ob-seed-btn {
        display: inline-flex; align-items: center; gap: 6px; font-size: .75rem; font-weight: 700;
        border: 1.5px dashed #c7d2fe; background: #f8faff; color: #4338ca;
        border-radius: 10px; padding: 7px 12px; cursor: pointer; font-family: inherit;
        transition: all .15s ease;
    }
    .ob-seed-btn:hover { border-style: solid; background: #eef2ff; transform: translateY(-1px); }
</style>
