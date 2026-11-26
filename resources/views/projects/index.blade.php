@extends('layouts.app')

@section('title', __('Projects'))

@push('styles')
<style>
    /* ─── Page Shell ───────────────────────────────────────────── */
    .main-content {
        padding: 14px 16px;
        background: #f7f8fa;
        min-height: 100vh;
    }

    /* ─── Gradient Page Header ─────────────────────────────────── */
    .content-header {
        background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
        border-radius: 10px;
        padding: 14px 20px;
        color: white;
        margin-bottom: 12px;
        position: relative;
        overflow: hidden;
        border: 1px solid var(--primary-500);
        box-shadow: 0 4px 20px rgba(99,102,241,.35);
    }

    .content-header::before {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 90px; height: 90px;
        background: rgba(255,255,255,.08);
        border-radius: 50%;
        transform: translate(22px,-22px);
    }

    .content-title {
        color: white;
        font-weight: 700;
        font-size: 18px;
        margin-bottom: 2px;
        position: relative;
        z-index: 1;
    }

    .content-subtitle {
        color: rgba(255,255,255,.8);
        font-size: 12px;
        margin: 0;
        font-weight: 400;
        position: relative;
        z-index: 1;
    }

    .cu-new-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background: white;
        color: #6d28d9;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all .15s;
        position: relative;
        z-index: 1;
    }

    .cu-new-btn:hover { background: #f5f3ff; color: #5b21b6; }

    /* ─── Stat Strip ───────────────────────────────────────────── */
    .cu-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 12px;
    }

    .cu-stat {
        background: white;
        border: 1px solid #e3e4e8;
        border-radius: 10px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .cu-stat:hover { border-color: #ddd6fe; box-shadow: 0 4px 12px rgba(76,29,149,.07); }

    .cu-stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .cu-stat-icon.total    { background: #ede9fe; color: #7c3aed; }
    .cu-stat-icon.active   { background: #dbeafe; color: #2563eb; }
    .cu-stat-icon.done     { background: #dcfce7; color: #16a34a; }
    .cu-stat-icon.overdue  { background: #fee2e2; color: #dc2626; }

    .cu-stat-num {
        font-size: 20px;
        font-weight: 800;
        color: #1a1d23;
        line-height: 1;
    }

    .cu-stat-label {
        font-size: 11px;
        color: #8a8f98;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    /* ─── Toolbar ──────────────────────────────────────────────── */
    .cu-toolbar {
        background: white;
        border: 1px solid #e3e4e8;
        border-radius: 10px;
        padding: 8px 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        box-shadow: 0 1px 2px rgba(16,24,40,.04);
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .cu-toolbar:focus-within {
        border-color: #c4b5fd;
        box-shadow: 0 0 0 3px rgba(124,58,237,.08);
    }

    .cu-search-wrap {
        position: relative;
        flex: 1;
        min-width: 180px;
    }

    .cu-search-wrap > i {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: #adb0b8;
    }

    .cu-search {
        width: 100%;
        height: 30px;
        padding: 0 10px 0 30px;
        border: 1px solid #d3d5db;
        border-radius: 8px;
        background: #fafbfc;
        font-size: 13px;
        color: #1a1d23;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .cu-search:focus { border-color: #7c3aed; background: white; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .cu-search::placeholder { color: #b0b4be; }

    .cu-filter {
        height: 30px;
        padding: 0 10px;
        border: 1px solid #d3d5db;
        border-radius: 8px;
        background: white;
        font-size: 12px;
        color: #374151;
        outline: none;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .cu-filter:hover { border-color: #adb0b8; }

    .cu-filter:focus { border-color: #7c3aed; }

    .cu-view-toggle {
        display: flex;
        border: 1px solid #d3d5db;
        border-radius: 8px;
        overflow: hidden;
        background: white;
    }

    .cu-view-btn {
        width: 30px;
        height: 30px;
        border: none;
        background: white;
        color: #8a8f98;
        font-size: 13px;
        cursor: pointer;
        transition: all .15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cu-view-btn:first-child { border-right: 1px solid #d3d5db; }
    .cu-view-btn.active { background: #7c3aed; color: white; box-shadow: inset 0 0 0 1px #7c3aed, 0 2px 6px rgba(124,58,237,.35); }
    .cu-view-btn:not(.active):hover { background: #f3f4f6; color: #374151; }

    /* ─── Grid View ────────────────────────────────────────────── */
    .cu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 10px;
        align-items: start;
    }

    .cu-card {
        background: white;
        border: 1px solid #e7e8ec;
        border-radius: 14px;
        overflow: hidden;
        transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
        position: relative;
    }

    .cu-card:hover {
        box-shadow: 0 12px 32px rgba(76,29,149,.12);
        border-color: #c4b5fd;
        transform: translateY(-2px);
    }

    .cu-color-bar {
        height: 4px;
        width: 100%;
    }

    .cu-card-body {
        padding: 14px 16px;
    }

    .cu-card-top {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 8px;
    }

    .cu-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 16px;
        color: white;
        flex-shrink: 0;
    }

    .cu-card-text {
        flex: 1 1 auto;
        min-width: 0;
    }

    .cu-card-name {
        font-size: 13px;
        font-weight: 700;
        color: #1a1d23;
        margin: 0 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .cu-card-desc {
        font-size: 11px;
        color: #8a8f98;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.5;
    }

    .cu-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .cu-chip.not-started { background: #f1f2f4; color: #6b7385; }
    .cu-chip.in-progress { background: #e8f0fe; color: #2563eb; }
    .cu-chip.completed   { background: #e6f9f0; color: #16a34a; }
    .cu-chip.closed      { background: #f3f4f6; color: #9ca3af; }

    .cu-progress-wrap {
        margin: 8px 0;
    }

    .cu-progress-bar-bg {
        height: 6px;
        background: #eef0f3;
        border-radius: 6px;
        overflow: hidden;
    }

    .cu-progress-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .3s;
    }

    .cu-card-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 11px;
        color: #8a8f98;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .cu-card-meta i { font-size: 11px; }
    .cu-card-meta .overdue { color: #dc2626; font-weight: 600; }

    .cu-team-avatars {
        display: flex;
    }

    .cu-team-avatar {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #7c3aed;
        color: white;
        font-size: 9px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: -5px;
        border: 2px solid white;
    }

    .cu-team-avatar:first-child { margin-left: 0; }

    .cu-card-actions {
        display: flex;
        gap: 4px;
        padding-top: 8px;
        border-top: 1px solid #f0f1f3;
    }

    .cu-action-btn {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid #e3e4e8;
        background: white;
        color: #8a8f98;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        text-decoration: none;
        transition: all .15s ease;
        cursor: pointer;
    }

    .cu-action-btn:hover { background: #f3f4f6; color: #374151; border-color: #adb0b8; transform: translateY(-1px); }
    .cu-action-btn:active { transform: translateY(0); }
    .cu-action-btn.danger:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
    .cu-action-btn:focus-visible,
    .cu-view-btn:focus-visible,
    .cu-filter:focus-visible,
    .cu-search:focus-visible { outline: 2px solid #7c3aed; outline-offset: 2px; }

    /* ─── List View ────────────────────────────────────────────── */
    .cu-list { display: none; flex-direction: column; gap: 0; }

    .cu-list-header {
        display: grid;
        grid-template-columns: 2fr 1fr 120px 80px 90px;
        gap: 12px;
        padding: 6px 14px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #8a8f98;
        background: #fafbfc;
        border: 1px solid #e3e4e8;
        border-radius: 8px 8px 0 0;
    }

    .cu-list-row {
        display: grid;
        grid-template-columns: 2fr 1fr 120px 80px 90px;
        gap: 12px;
        padding: 10px 14px;
        background: white;
        border: 1px solid #e3e4e8;
        border-top: none;
        align-items: center;
        transition: background .12s;
    }

    .cu-list-row:last-child { border-radius: 0 0 8px 8px; }
    .cu-list-row:hover { background: #fafbfc; }

    .cu-list-name {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1a1d23;
        min-width: 0;
    }

    .cu-list-name span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cu-list-avatar {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11px;
        color: white;
        flex-shrink: 0;
    }

    .cu-list-meta {
        font-size: 11px;
        color: #8a8f98;
    }

    .cu-list-progress {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: #6b7385;
    }

    .cu-list-pb {
        flex: 1;
        height: 4px;
        background: #f0f1f3;
        border-radius: 4px;
        overflow: hidden;
    }

    .cu-list-pb-fill {
        height: 100%;
        border-radius: 4px;
    }

    .cu-list-actions {
        display: flex;
        gap: 4px;
        justify-content: flex-end;
    }

    /* ─── Empty State ──────────────────────────────────────────── */
    .cu-empty {
        text-align: center;
        padding: 48px 20px;
        background: white;
        border: 1px dashed #c4b5fd;
        border-radius: 14px;
    }

    .cu-empty-icon {
        width: 52px;
        height: 52px;
        background: #ede9fe;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 22px;
        color: #7c3aed;
    }

    .cu-empty h5 { font-size: 15px; font-weight: 700; color: #1a1d23; margin-bottom: 4px; }
    .cu-empty p  { font-size: 12px; color: #8a8f98; margin-bottom: 14px; }

    .cu-empty-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        background: #7c3aed;
        color: white;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .cu-empty-btn:hover { background: #6d28d9; color: white; }

    /* ─── Alert ────────────────────────────────────────────────── */
    .cu-alert {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #e6f9f0;
        border: 1px solid #6ee7b7;
        color: #065f46;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 10px;
    }

    .cu-alert-close {
        margin-left: auto;
        background: none;
        border: none;
        color: #065f46;
        cursor: pointer;
        font-size: 14px;
    }

    /* ─── Tree View ────────────────────────────────────────────── */
    .cu-tree {
        display: none;
        background: white;
        border: 1px solid #e3e4e8;
        border-radius: 8px;
        overflow: hidden;
    }
    .cu-tree-node { border-bottom: 1px solid #f0f1f3; }
    .cu-tree-node:last-child { border-bottom: none; }
    .cu-tree-row {
        display: flex; align-items: center; gap: 8px;
        padding: 9px 12px 9px calc(12px + var(--depth, 0) * 22px);
        transition: background .15s ease;
        border-radius: 6px;
    }
    .cu-tree-row:hover { background: #fafbfc; }
    .cu-tree-toggle {
        width: 22px; height: 22px; border: 1px solid #e3e4e8; background: white;
        border-radius: 6px; color: #8a8f98; font-size: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .cu-tree-toggle i { transition: transform .15s; }
    .cu-tree-toggle.collapsed i { transform: rotate(-90deg); }
    .cu-tree-spacer { width: 22px; flex-shrink: 0; }
    .cu-tree-name {
        font-size: 13px; font-weight: 600; color: #1a1d23; text-decoration: none;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
    }
    .cu-tree-name:hover { color: #7c3aed; }
    .cu-tree-type {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        background: #f1f2f4; color: #6b7385; border-radius: 20px; padding: 1px 8px; flex-shrink: 0;
    }
    .cu-tree-meta { font-size: 11px; color: #8a8f98; margin-left: auto; white-space: nowrap; }
    .cu-tree-progress { display: flex; align-items: center; gap: 6px; min-width: 110px; font-size: 11px; color: #6b7385; }
    .cu-tree-pb { flex: 1; height: 4px; background: #f0f1f3; border-radius: 4px; overflow: hidden; }
    .cu-tree-pb-fill { height: 100%; background: #7c3aed; border-radius: 4px; }
    .cu-tree-actions { display: flex; gap: 4px; }
    .cu-tree-children { background: #fcfcfd; }

    /* ─── Grouped Card: sub-projects nested inside the parent ─── */
    .cu-card.is-group { overflow: hidden; }
    .cu-sub {
        margin: 0 -14px -12px;
        border-top: 1px solid #f0f1f3;
        background: #fafbfc;
    }
    .cu-sub-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; padding: 7px 12px 6px;
        font-size: 11px; font-weight: 700; color: #6b7280;
        text-transform: uppercase; letter-spacing: .4px;
    }
    .cu-sub-head .cu-sub-title { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
    .cu-sub-head .cu-sub-title i { color: #7c3aed; font-size: 12px; }
    .cu-sub-count {
        background: #ede9fe; color: #6d28d9;
        border-radius: 20px; padding: 0 8px; font-size: 10px; line-height: 18px;
    }
    .cu-sub-add {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 11px; font-weight: 600; color: #7c3aed; text-decoration: none;
        text-transform: none; letter-spacing: 0;
        padding: 2px 8px; border-radius: 6px;
    }
    .cu-sub-add:hover { background: #ede9fe; color: #5b21b6; }
    .cu-sub-list {
        display: flex; flex-direction: column;
        max-height: 160px; overflow: hidden; position: relative;
    }
    .cu-sub-list::after {
        content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 34px;
        background: linear-gradient(transparent, #fafbfc); pointer-events: none;
    }
    .cu-sub-row {
        display: flex; align-items: center; gap: 8px;
        padding: 7px 12px;
        padding-inline-start: calc(12px + var(--sub-depth, 0) * 20px);
        border-top: 1px solid #f0f1f3; text-decoration: none;
        transition: background .12s;
    }
    .cu-sub-row:hover { background: #f5f3ff; }
    .cu-sub-type {
        font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        border: 1px solid; border-radius: 20px; padding: 1px 7px; flex-shrink: 0;
    }
    .cu-sub-name {
        flex: 1 1 auto; min-width: 0; font-size: 12px; font-weight: 600; color: #1a1d23;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cu-sub-row:hover .cu-sub-name { color: #6d28d9; }
    .cu-sub-meta { font-size: 10px; color: #8a8f98; flex-shrink: 0; }
    .cu-sub-pb { width: 56px; height: 4px; background: #eceef2; border-radius: 4px; overflow: hidden; flex-shrink: 0; }
    .cu-sub-pb-fill { display: block; height: 100%; border-radius: 4px; }
    .cu-sub-pct { font-size: 10px; color: #6b7380; min-width: 30px; text-align: end; flex-shrink: 0; }
    .cu-sub-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .cu-sub-dot.not-started { background: #d1d5db; }
    .cu-sub-dot.in-progress { background: #2563eb; }
    .cu-sub-dot.completed   { background: #16a34a; }
    .cu-sub-dot.closed      { background: #9ca3af; }

    /* ─── Card Expand Modal ──────────────────────────────────── */
    .cu-modal-backdrop {
        position: fixed; inset: 0; z-index: 1050;
        background: rgba(15,18,30,.55);
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .cu-modal-backdrop[hidden] { display: none; }
    .cu-modal {
        background: white; border-radius: 12px;
        width: min(640px, 100%); max-height: 85vh;
        display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
    }
    .cu-modal-head {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border-bottom: 1px solid #f0f1f3;
        flex-shrink: 0;
    }
    .cu-modal-title {
        flex: 1 1 auto; min-width: 0;
        font-size: 14px; font-weight: 700; color: #1a1d23;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cu-modal-sub { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .cu-modal-body { overflow-y: auto; padding: 4px 0 10px; }
    .cu-modal-body .cu-sub-row { border-top: 1px solid #f0f1f3; }
    .cu-modal-body .cu-sub-row:first-child { border-top: none; }

    /* ─── Search palette ─────────────────────────────────────── */
    .cu-search-trigger {
        width: 100%; height: 30px;
        display: flex; align-items: center; gap: 8px;
        padding: 0 10px;
        border: 1px solid #d3d5db; border-radius: 8px;
        background: #fafbfc; color: #8a8f98;
        font-size: 13px; cursor: pointer; text-align: start;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .cu-search-trigger:hover { border-color: #adb0b8; background: white; }
    .cu-search-trigger > i { font-size: 13px; }
    .cu-search-trigger span {
        flex: 1 1 auto; min-width: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        text-align: start;
    }
    .cu-search-trigger kbd, .cu-search-box kbd, .cu-search-foot kbd {
        font-family: inherit; font-size: 10px; font-weight: 700;
        background: #f1f2f4; color: #6b7380;
        border: 1px solid #e3e4e8; border-bottom-width: 2px;
        border-radius: 5px; padding: 1px 6px; white-space: nowrap;
    }
    .cu-search-backdrop { align-items: flex-start; padding: 12vh 20px 20px; }
    .cu-search-modal {
        background: white; border-radius: 14px;
        width: min(560px, 100%);
        max-height: 70vh; max-height: 70dvh;
        display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 24px 70px rgba(0,0,0,.35);
    }
    .cu-search-box {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 14px 14px 16px; border-bottom: 1px solid #f0f1f3;
        flex-shrink: 0;
    }
    .cu-search-box > i { color: #7c3aed; font-size: 16px; flex-shrink: 0; }
    .cu-search-box input {
        flex: 1 1 auto; min-width: 0;
        border: none; outline: none; background: transparent;
        font-size: 15px; color: #1a1d23;
    }
    .cu-search-box input::placeholder { color: #b0b4be; }
    .cu-search-results { overflow-y: auto; padding: 8px; }
    .cu-sr-group {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .6px; color: #8a8f98;
        padding: 8px 10px 4px;
    }
    .cu-sr-row {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px; border-radius: 8px;
        text-decoration: none; color: #1a1d23;
    }
    .cu-sr-row:hover, .cu-sr-row.is-active { background: #f5f3ff; }
    .cu-sr-icon {
        width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0;
        background: #ede9fe; color: #6d28d9;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .cu-sr-icon.sub { background: #f1f2f4; color: #6b7385; }
    .cu-sr-name {
        flex: 1 1 auto; min-width: 0;
        font-size: 13px; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cu-sr-name mark {
        background: #fde68c; color: inherit;
        border-radius: 3px; padding: 0 1px;
    }
    .cu-sr-empty { text-align: center; padding: 28px 16px; color: #8a8f98; font-size: 13px; }
    .cu-sr-more { text-align: center; padding: 8px; color: #8a8f98; font-size: 11px; }
    .cu-search-foot {
        display: flex; align-items: center; gap: 14px;
        padding: 8px 16px; border-top: 1px solid #f0f1f3;
        font-size: 11px; color: #8a8f98; flex-shrink: 0;
    }

    /* ─── Grouped List: child rows ─────────────────────────────── */
    .cu-list-row.is-child { background: #fcfcfd; }
    .cu-list-row.is-child:hover { background: #f5f3ff; }
    .cu-list-row.is-child .cu-list-name { padding-inline-start: calc(var(--sub-depth, 0) * 24px); }
    .cu-child-guides {
        width: 14px; flex-shrink: 0; align-self: stretch;
        border-inline-start: 2px solid #ddd6fe; border-bottom: 2px solid #ddd6fe;
        border-end-start-radius: 6px; margin-inline-end: 2px; margin-bottom: 6px;
    }

    .cu-toolbar-row { display: contents; }

    /* ─── Responsive ───────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .cu-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .cu-stat { padding: 10px 12px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 10px 10px 28px; }

        /* Header stacks, full-width CTA */
        .content-header { padding: 12px 14px; }
        .content-header .d-flex { flex-direction: column; align-items: stretch !important; gap: 10px; }
        .content-title  { font-size: 16px; }
        .content-subtitle { font-size: 11px; }
        .cu-new-btn { width: 100%; justify-content: center; padding: 10px 14px; font-size: 13px; min-height: 42px; }

        /* Stats: compact 2x2 */
        .cu-stats { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .cu-stat { padding: 10px 12px; gap: 8px; }
        .cu-stat-icon { width: 28px; height: 28px; font-size: 13px; border-radius: 7px; }
        .cu-stat-num { font-size: 17px; }

        /* Toolbar: search on its own row, controls in a scrollable row */
        .cu-search-wrap { flex: 1 1 100%; min-width: 0; }
        .cu-search { height: 40px; font-size: 14px; }
        .cu-search-trigger { height: 40px; font-size: 14px; }
        .cu-search-backdrop { padding: 6vh 12px 12px; }
        .cu-search-modal { max-height: 82vh; max-height: 82dvh; }
        .cu-toolbar-row { display: flex; gap: 8px; width: 100%; overflow-x: auto; padding-bottom: 2px; }
        .cu-filter { height: 40px; font-size: 13px; flex: 0 0 auto; }
        .cu-view-toggle { flex-shrink: 0; margin-inline-start: auto; }
        .cu-view-btn { width: 40px; height: 40px; }

        /* Grid: single column */
        .cu-grid  { grid-template-columns: 1fr; }

        /* Cards: comfortable touch targets */
        .cu-action-btn { width: 40px; height: 40px; font-size: 14px; }
        .cu-card-actions { gap: 8px; }

        /* List: headerless two-column rows (name / status / actions) */
        .cu-list-header { display: none; }
        .cu-list-row { grid-template-columns: 1fr auto auto; padding: 10px 12px; gap: 8px; }
        .cu-list-header + .cu-list-row { border-radius: 8px 8px 0 0; border-top: 1px solid #e3e4e8; }
        .cu-list-row .col-meta,
        .cu-list-row .col-prog { display: none; }
        .cu-list-row.is-child .cu-list-name { padding-inline-start: calc(min(var(--sub-depth, 0), 3) * 12px); }

        /* Tree: essentials only */
        .cu-tree-row { padding: 10px 12px 10px calc(12px + var(--depth, 0) * 12px); }
        .cu-tree-toggle { width: 32px; height: 32px; font-size: 12px; }
        .cu-tree-meta, .cu-tree-progress { display: none; }

        /* Sub-rows: tighter indent on small screens */
        .cu-sub-row { padding-inline-start: calc(12px + min(var(--sub-depth, 0), 3) * 12px); }

        /* Expand modal becomes a bottom sheet */
        .cu-modal-backdrop { padding: 0; align-items: flex-end; }
        .cu-modal { width: 100%; max-height: 92vh; max-height: 92dvh; border-radius: 16px 16px 0 0; }
    }

    @media (max-width: 480px) {
        .cu-stats { gap: 6px; }
        .cu-stat { padding: 8px 10px; }
        .cu-stat-num { font-size: 15px; }
        .cu-stat-label { font-size: 10px; }
        .cu-card-body { padding: 10px 12px; }
        .cu-sub-pb, .cu-sub-pct { display: none; }
        .cu-tree-type { display: none; }
        .cu-team-avatars { display: none; }
    }

    /* ─── Reduced motion ───────────────────────────────────────── */
    @media (prefers-reduced-motion: reduce) {
        .cu-card, .cu-card:hover,
        .cu-action-btn, .cu-action-btn:hover,
        .cu-view-btn, .cu-stat, .cu-tree-row, .cu-sub-row {
            transition: none;
            transform: none;
        }
    }
</style>
@endpush

@section('content')
<div class="main-content">

    {{-- ── Gradient Header ─────────────────────────────────────── --}}
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center" style="position:relative;z-index:1;">
            <div>
                <h1 class="content-title">{{ __('Projects') }}</h1>
                <p class="content-subtitle">{{ __('Manage and track your project progress') }}</p>
            </div>
            <a href="{{ route('projects.create') }}" class="cu-new-btn">
                <i class="bi bi-plus-lg"></i>{{ __('New Project') }}
            </a>
        </div>
    </div>

    {{-- ── Stat Strip ──────────────────────────────────────────── --}}
    @php
        $totalProjects    = $projects->count();
        $activeProjects   = $projects->where('status', 'in_progress')->count();
        $doneProjects     = $projects->where('status', 'completed')->count();
        $overdueProjects  = $projects->filter(fn($p) => $p->end_date && $p->end_date->isPast() && !in_array($p->status, ['completed','closed']))->count();
    @endphp
    <div class="cu-stats">
        <div class="cu-stat">
            <div class="cu-stat-icon total"><i class="bi bi-folder2"></i></div>
            <div><div class="cu-stat-num">{{ $totalProjects }}</div><div class="cu-stat-label">{{ __('Total') }}</div></div>
        </div>
        <div class="cu-stat">
            <div class="cu-stat-icon active"><i class="bi bi-lightning-charge"></i></div>
            <div><div class="cu-stat-num">{{ $activeProjects }}</div><div class="cu-stat-label">{{ __('Active') }}</div></div>
        </div>
        <div class="cu-stat">
            <div class="cu-stat-icon done"><i class="bi bi-check-circle"></i></div>
            <div><div class="cu-stat-num">{{ $doneProjects }}</div><div class="cu-stat-label">{{ __('Completed') }}</div></div>
        </div>
        <div class="cu-stat">
            <div class="cu-stat-icon overdue"><i class="bi bi-exclamation-circle"></i></div>
            <div><div class="cu-stat-num">{{ $overdueProjects }}</div><div class="cu-stat-label">{{ __('Overdue') }}</div></div>
        </div>
    </div>

    {{-- ── Toolbar ─────────────────────────────────────────────── --}}
    <div class="cu-toolbar">
        <div class="cu-search-wrap">
            <button type="button" class="cu-search-trigger" id="projectSearchBtn">
                <i class="bi bi-search"></i>
                <span>{{ __('Search projects...') }}</span>
                <kbd>Ctrl K</kbd>
            </button>
            <input type="hidden" id="projectSearch" value="">
        </div>
        <div class="cu-toolbar-row">
        <select class="cu-filter" id="statusFilter">
            <option value="">{{ __('All') }} {{ __('Status') }}</option>
            <option value="not_started">{{ __('Pending') }}</option>
            <option value="in_progress">{{ __('In Progress') }}</option>
            <option value="completed">{{ __('Completed') }}</option>
            <option value="closed">{{ __('Archived') }}</option>
        </select>
        <select class="cu-filter" id="sortFilter">
            <option value="name">{{ __('Title') }}</option>
            <option value="created_at">{{ __('Newest') }}</option>
            <option value="progress">{{ __('Progress') }}</option>
        </select>
        <div class="cu-view-toggle">
            <button class="cu-view-btn active" data-view="grid" title="{{ __('Grid') }}"><i class="bi bi-grid-3x3-gap"></i></button>
            <button class="cu-view-btn" data-view="list" title="{{ __('List') }}"><i class="bi bi-list-ul"></i></button>
            <button class="cu-view-btn" data-view="tree" title="{{ __('Tree') }}"><i class="bi bi-diagram-3"></i></button>
        </div>
        </div>
    </div>

    {{-- ── Projects ─────────────────────────────────────────────── --}}
    @if($projects->count() > 0)

        {{-- Grid: one card per root project, children nested inside --}}
        <div class="cu-grid" id="cuGrid">
            @foreach($roots as $project)
                @php
                    $totalTasks     = $project->tasks->count();
                    $completedTasks = $project->tasks->where('status', 'completed')->count();
                    $progress       = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
                    $colors         = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899'];
                    $projectColor   = $colors[strlen($project->name) % count($colors)];
                    $chipClass      = match($project->status) {
                        'in_progress' => 'in-progress',
                        'completed'   => 'completed',
                        'closed'      => 'closed',
                        default       => 'not-started'
                    };
                    $chipLabel = match($project->status) {
                        'in_progress' => 'In Progress',
                        'completed'   => 'Completed',
                        'closed'      => 'Closed',
                        default       => 'Not Started'
                    };
                    $teamCount = $project->teamMembers ? $project->teamMembers->count() : 0;
                    $isOverdue = $project->end_date && $project->end_date->isPast() && !in_array($project->status, ['completed','closed']);
                    $childCount = $project->childrenRecursive->count();
                    $groupName = $groupSearch[$project->id] ?? strtolower($project->name);
                @endphp
                <div class="cu-card project-item {{ $childCount > 0 ? 'is-group' : '' }}"
                     data-self="{{ strtolower($project->name) }}"
                     data-name="{{ $groupName }}"
                     data-status="{{ $project->status }}"
                     data-progress="{{ $progress }}"
                     data-created="{{ $project->created_at->timestamp }}">
                    <div class="cu-color-bar" style="background:{{ $projectColor }};"></div>
                    <div class="cu-card-body">
                        <div class="cu-card-top">
                            <div class="cu-avatar" style="background:{{ $projectColor }};">
                                {{ name_initials($project->name) }}
                            </div>
                            <div class="cu-card-text">
                                <div class="cu-card-name" title="{{ $project->name }}">{{ $project->name }}</div>
                                <div class="cu-card-desc">{{ strip_tags($project->description ?? '') ?: __('No description') }}</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="cu-chip {{ $chipClass }}">
                                <i class="bi bi-circle-fill" style="font-size:6px;"></i>{{ __($chipLabel) }}
                            </span>
                            <span style="font-size:11px; color:#8a8f98;">{{ round($progress) }}%</span>
                        </div>

                        <div class="cu-progress-wrap">
                            <div class="cu-progress-bar-bg">
                                <div class="cu-progress-bar-fill" style="background:{{ $projectColor }}; width:{{ $progress }}%;"></div>
                            </div>
                        </div>

                        <div class="cu-card-meta">
                            @if($project->end_date)
                                <span class="{{ $isOverdue ? 'overdue' : '' }}">
                                    <i class="bi bi-calendar3"></i>
                                    @if($isOverdue) {{ __('Overdue') }}
                                    @else {{ $project->end_date->format('M d') }}
                                    @endif
                                </span>
                            @endif
                            @if($totalTasks > 0)
                                <span><i class="bi bi-check2-square"></i>{{ $completedTasks }}/{{ $totalTasks }}</span>
                            @endif
                            @if($teamCount > 0)
                                <span class="d-flex align-items-center gap-1">
                                    <div class="cu-team-avatars">
                                        @foreach($project->teamMembers->take(3) as $member)
                                            <div class="cu-team-avatar" title="{{ $member->name }}">{{ name_initials($member->name) }}</div>
                                        @endforeach
                                        @if($teamCount > 3)<div class="cu-team-avatar">+{{ $teamCount - 3 }}</div>@endif
                                    </div>
                                    {{ $teamCount }}
                                </span>
                            @endif
                        </div>

                        @if($childCount > 0)
                            <div class="cu-sub">
                                <div class="cu-sub-head">
                                    <span class="cu-sub-title">
                                        <i class="bi bi-diagram-3"></i>{{ __('Sub-projects') }}
                                        <span class="cu-sub-count">{{ $childCount }}</span>
                                    </span>
                                    <a href="{{ route('projects.create', ['parent' => $project->id]) }}" class="cu-sub-add">
                                        <i class="bi bi-plus-lg"></i>{{ __('Add') }}
                                    </a>
                                </div>
                                <div class="cu-sub-list">
                                    @include('projects._group-children', ['nodes' => $project->childrenRecursive, 'depth' => 0])
                                </div>
                            </div>
                        @endif

                        <div class="cu-card-actions">
                            @if($childCount > 0)
                                <button type="button" class="cu-action-btn cu-expand-btn" title="{{ __('Expand') }}"><i class="bi bi-arrows-fullscreen"></i></button>
                            @endif
                            <a href="{{ route('projects.tasks.index', $project) }}" class="cu-action-btn" title="{{ __('Tasks') }}"><i class="bi bi-list-task"></i></a>
                            <a href="{{ route('projects.show', $project) }}" class="cu-action-btn" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('projects.edit', $project) }}?return_to={{ urlencode(request()->fullUrl()) }}" class="cu-action-btn" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('projects.destroy', $project) }}" method="POST" class="d-inline"
                                  onsubmit="return confirmSwal(this, @json(__('Delete this project? This cannot be undone.')), { isDelete: true })">
                                @csrf @method('DELETE')
                            <button type="submit" class="cu-action-btn danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                    </div>
                </div>
                </div>
            @endforeach
        </div>

        {{-- List: parent rows with nested child rows --}}
        <div class="cu-list" id="cuList">
            <div class="cu-list-header">
                <div>{{ __('Project') }}</div>
                <div class="col-meta">{{ __('Due Date') }}</div>
                <div class="col-prog">{{ __('Progress') }}</div>
                <div>{{ __('Status') }}</div>
                <div></div>
            </div>
            @foreach($roots as $project)
                @php
                    $totalTasks     = $project->tasks->count();
                    $completedTasks = $project->tasks->where('status', 'completed')->count();
                    $progress       = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
                    $colors         = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899'];
                    $projectColor   = $colors[strlen($project->name) % count($colors)];
                    $chipClass      = match($project->status) { 'in_progress'=>'in-progress','completed'=>'completed','closed'=>'closed',default=>'not-started'};
                    $chipLabel      = match($project->status) { 'in_progress'=>'In Progress','completed'=>'Completed','closed'=>'Closed',default=>'Not Started'};
                    $isOverdue      = $project->end_date && $project->end_date->isPast() && !in_array($project->status, ['completed','closed']);
                    $groupName      = $groupSearch[$project->id] ?? strtolower($project->name);
                    $childCount     = $project->childrenRecursive->count();
                @endphp
                <div class="cu-list-row project-item"
                     data-id="{{ $project->id }}"
                     data-self="{{ strtolower($project->name) }}"
                     data-name="{{ $groupName }}"
                     data-status="{{ $project->status }}"
                     data-progress="{{ $progress }}"
                     data-created="{{ $project->created_at->timestamp }}">
                    <div class="cu-list-name">
                        <div class="cu-list-avatar" style="background:{{ $projectColor }};">{{ name_initials($project->name) }}</div>
                        <span title="{{ $project->name }}">{{ $project->name }}</span>
                        @if($childCount > 0)
                            <span class="cu-sub-count">{{ $childCount }}</span>
                        @endif
                    </div>
                    <div class="cu-list-meta col-meta">
                        @if($project->end_date)
                            <span class="{{ $isOverdue ? 'text-danger fw-semibold' : '' }}" style="font-size:11px;">
                                @if($isOverdue)<i class="bi bi-exclamation-circle me-1"></i>{{ __('Overdue') }}
                                @else {{ $project->end_date->format('M d, Y') }}
                                @endif
                            </span>
                        @else <span style="color:#d3d5db;">—</span>
                        @endif
                    </div>
                    <div class="cu-list-progress col-prog">
                        <div class="cu-list-pb">
                            <div class="cu-list-pb-fill" style="background:{{ $projectColor }}; width:{{ $progress }}%;"></div>
                        </div>
                        <span>{{ round($progress) }}%</span>
                    </div>
                    <div><span class="cu-chip {{ $chipClass }}"><i class="bi bi-circle-fill" style="font-size:6px;"></i>{{ __($chipLabel) }}</span></div>
                    <div class="cu-list-actions">
                        <a href="{{ route('projects.show', $project) }}" class="cu-action-btn" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('projects.edit', $project) }}?return_to={{ urlencode(request()->fullUrl()) }}" class="cu-action-btn" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('projects.destroy', $project) }}" method="POST" class="d-inline"
                              onsubmit="return confirmSwal(this, @json(__('Delete this project?')), { isDelete: true })">
                            @csrf @method('DELETE')
                            <button type="submit" class="cu-action-btn danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                @if($project->childrenRecursive->count() > 0)
                    @include('projects._list-children', ['nodes' => $project->childrenRecursive, 'depth' => 0, 'rootId' => $project->id])
                @endif
            @endforeach
        </div>

        {{-- Tree --}}
        <div class="cu-tree" id="cuTree">
            @forelse($roots as $root)
                @include('projects._tree-node', ['project' => $root, 'depth' => 0])
            @empty
                <div class="cu-empty" style="border:none;">
                    <div class="cu-empty-icon"><i class="bi bi-diagram-3"></i></div>
                    <h5>{{ __('No projects yet') }}</h5>
                    <p>{{ __('Create your first project to start organising your work.') }}</p>
                </div>
            @endforelse
        </div>

    @else
        <div class="cu-empty">
            <div class="cu-empty-icon"><i class="bi bi-folder-plus"></i></div>
            <h5>{{ __('No projects yet') }}</h5>
            <p>{{ __('Create your first project to start organising your work.') }}</p>
            <a href="{{ route('projects.create') }}" class="cu-empty-btn"><i class="bi bi-plus-lg"></i>{{ __('Create Project') }}</a>
        </div>
    @endif

</div>

{{-- Card expand modal: full sub-project tree of one card --}}
<div class="cu-modal-backdrop" id="cuCardModal" hidden>
    <div class="cu-modal" role="dialog" aria-modal="true" aria-labelledby="cuModalTitle">
        <div class="cu-modal-head">
            <div class="cu-modal-title" id="cuModalTitle"></div>
            <div class="cu-modal-sub" id="cuModalSub"></div>
            <button type="button" class="cu-action-btn" id="cuModalClose" title="{{ __('Close') }}"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cu-modal-body" id="cuModalBody"></div>
    </div>
</div>

{{-- Search palette: fast project navigation (Ctrl+K) --}}
<div class="cu-modal-backdrop cu-search-backdrop" id="cuSearchModal" hidden
     data-grp-projects="{{ __('Projects') }}"
     data-grp-subs="{{ __('Sub-projects') }}"
     data-suggest="{{ __('Suggestions') }}"
     data-empty="{{ __('No matching projects.') }}"
     data-more="{{ __('more — refine your search') }}">
    <div class="cu-search-modal" role="dialog" aria-modal="true" aria-label="{{ __('Search projects') }}">
        <div class="cu-search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="cuSearchInput" placeholder="{{ __('Search projects...') }}" autocomplete="off">
            <button type="button" class="cu-action-btn" id="cuSearchClose" title="{{ __('Close') }}"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cu-search-results" id="cuSearchResults"></div>
        <div class="cu-search-foot">
            <span><kbd>↑↓</kbd> {{ __('navigate') }}</span>
            <span><kbd>↵</kbd> {{ __('open') }}</span>
            <span><kbd>esc</kbd> {{ __('close') }}</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput  = document.getElementById('projectSearch');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter   = document.getElementById('sortFilter');
    const viewBtns     = document.querySelectorAll('.cu-view-btn');
    const cuGrid       = document.getElementById('cuGrid');
    const cuList       = document.getElementById('cuList');

    function filterAndSort() {
        const term   = searchInput.value.toLowerCase();
        const status = statusFilter.value;
        const sort   = sortFilter.value;

        // Grid: one card per group. A card stays visible when it matches
        // itself or any nested sub-project matches; non-matching sub-rows
        // are hidden inside the card.
        cuGrid.querySelectorAll(':scope > .cu-card.project-item').forEach(card => {
            const selfMatch = (card.dataset.self || card.dataset.name || '').includes(term)
                && (!status || card.dataset.status === status);
            let childMatch = false;
            card.querySelectorAll('.cu-sub-row').forEach(row => {
                const ok = (row.dataset.name || '').includes(term)
                    && (!status || row.dataset.status === status);
                row.style.display = ok ? '' : 'none';
                if (ok) childMatch = true;
            });
            card.style.display = (selfMatch || childMatch) ? '' : 'none';
        });

        // List: child rows first, then parents (a parent stays visible
        // when it matches or any of its children is visible).
        const listChildren = Array.from(cuList.querySelectorAll('.cu-list-row.is-child'));
        listChildren.forEach(row => {
            const ok = (row.dataset.name || '').includes(term)
                && (!status || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
        });
        cuList.querySelectorAll(':scope > .cu-list-row.project-item:not(.is-child)').forEach(row => {
            const selfMatch = (row.dataset.self || row.dataset.name || '').includes(term)
                && (!status || row.dataset.status === status);
            const childVisible = listChildren.some(c => c.dataset.root === row.dataset.id && c.style.display !== 'none');
            row.style.display = (selfMatch || childVisible) ? '' : 'none';
        });

        // Tree nodes keep the simple own-match behaviour.
        document.querySelectorAll('#cuTree .project-item').forEach(item => {
            const nameMatch   = (item.dataset.name || '').includes(term);
            const statusMatch = !status || item.dataset.status === status;
            item.style.display = (nameMatch && statusMatch) ? '' : 'none';
        });

        // Sort visible group cards in grid (by the group's own values).
        const gridItems = Array.from(cuGrid.querySelectorAll(':scope > .cu-card.project-item')).filter(i => i.style.display !== 'none');
        gridItems.sort((a, b) => {
            if (sort === 'name')       return (a.dataset.self || '').localeCompare(b.dataset.self || '');
            if (sort === 'created_at') return parseInt(b.dataset.created) - parseInt(a.dataset.created);
            if (sort === 'progress')   return parseFloat(b.dataset.progress) - parseFloat(a.dataset.progress);
            return 0;
        });
        gridItems.forEach(i => cuGrid.appendChild(i));
    }

    function setView(view) {
        viewBtns.forEach(b => b.classList.toggle('active', b.dataset.view === view));
        const cuTree = document.getElementById('cuTree');
        cuGrid.style.display = view === 'grid' ? 'grid' : 'none';
        cuList.style.display = view === 'list' ? 'flex' : 'none';
        if (cuTree) cuTree.style.display = view === 'tree' ? 'block' : 'none';
    }

    searchInput.addEventListener('input', filterAndSort);
    statusFilter.addEventListener('change', filterAndSort);
    sortFilter.addEventListener('change', filterAndSort);
    viewBtns.forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));

    document.querySelectorAll('.cu-tree-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const hidden = target.style.display === 'none';
            target.style.display = hidden ? '' : 'none';
            btn.classList.toggle('collapsed', !hidden);
        });
    });

    // Card expand modal: show the full sub-project tree of one card.
    const cuModal      = document.getElementById('cuCardModal');
    const cuModalTitle = document.getElementById('cuModalTitle');
    const cuModalSub   = document.getElementById('cuModalSub');
    const cuModalBody  = document.getElementById('cuModalBody');

    function closeCardModal() {
        if (!cuModal) return;
        cuModal.hidden = true;
        document.body.style.overflow = '';
    }

    if (cuGrid && cuModal) {
        cuGrid.addEventListener('click', (e) => {
            const btn = e.target.closest('.cu-expand-btn');
            if (!btn) return;
            const card = btn.closest('.cu-card');
            if (!card) return;

            const nameEl = card.querySelector('.cu-card-name');
            cuModalTitle.textContent = nameEl
                ? (nameEl.getAttribute('title') || nameEl.textContent)
                : '';

            cuModalSub.innerHTML = '';
            const chip = card.querySelector('.cu-chip');
            if (chip) cuModalSub.appendChild(chip.cloneNode(true));

            cuModalBody.innerHTML = '';
            card.querySelectorAll('.cu-sub-list .cu-sub-row').forEach(row => {
                const clone = row.cloneNode(true);
                clone.style.display = '';
                cuModalBody.appendChild(clone);
            });

            cuModal.hidden = false;
            document.body.style.overflow = 'hidden';
        });

        document.getElementById('cuModalClose').addEventListener('click', closeCardModal);
        cuModal.addEventListener('click', (e) => {
            if (e.target === cuModal) closeCardModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !cuModal.hidden && cuSearchModal.hidden) closeCardModal();
        });
    }

    // ── Search palette (Ctrl+K): fast navigation + live grid filter ──
    const searchBtn      = document.getElementById('projectSearchBtn');
    const cuSearchModal  = document.getElementById('cuSearchModal');
    const paletteInput   = document.getElementById('cuSearchInput');
    const paletteResults = document.getElementById('cuSearchResults');
    let paletteItems = [];
    let paletteActive = -1;

    const dotFor = (s) => s === 'in_progress' ? 'in-progress'
        : (s === 'completed' ? 'completed' : (s === 'closed' ? 'closed' : 'not-started'));

    const escHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    // One index across all three views; same project linked twice is stored once.
    function buildPaletteIndex() {
        const seen = new Set();
        const items = [];
        const push = (name, url, kind, type, status) => {
            name = (name || '').trim();
            if (!name || !url || seen.has(url)) return;
            seen.add(url);
            items.push({
                name, url, kind, type: (type || '').trim(), status: status || '',
                hay: (name + ' ' + (type || '')).toLocaleLowerCase(),
            });
        };

        document.querySelectorAll('#cuGrid .cu-card.project-item').forEach((card) => {
            const nameEl = card.querySelector('.cu-card-name');
            let url = '';
            card.querySelectorAll('.cu-card-actions a').forEach((a) => {
                if (a.innerHTML.indexOf('bi-eye') !== -1) url = a.getAttribute('href');
            });
            push(nameEl ? (nameEl.getAttribute('title') || nameEl.textContent) : '', url, 'project', 'Project', card.dataset.status);
        });

        document.querySelectorAll('#cuTree .cu-tree-node').forEach((node) => {
            const a = node.querySelector(':scope > .cu-tree-row > .cu-tree-name');
            if (!a) return;
            const typeEl = node.querySelector(':scope > .cu-tree-row > .cu-tree-type');
            const isRoot = String(node.style.getPropertyValue('--depth')).trim() === '0';
            push(a.textContent, a.getAttribute('href'), isRoot ? 'project' : 'sub', typeEl ? typeEl.textContent : 'Project', node.dataset.status);
        });

        document.querySelectorAll('#cuGrid .cu-sub-row').forEach((a) => {
            const nameEl = a.querySelector('.cu-sub-name');
            const typeEl = a.querySelector('.cu-sub-type');
            push(nameEl ? nameEl.textContent : '', a.getAttribute('href'), 'sub', typeEl ? typeEl.textContent : '', a.dataset.status);
        });

        return items;
    }

    // Every token must match; prefix / word-boundary hits rank higher.
    function paletteScore(item, tokens) {
        let score = 0;
        for (const t of tokens) {
            const i = item.hay.indexOf(t);
            if (i === -1) return -1;
            score += (i === 0 || /[\s\-_./]/.test(item.hay[i - 1])) ? 2 : 1;
        }
        if (item.name.toLocaleLowerCase().indexOf(tokens.join(' ')) === 0) score += 3;

        return score;
    }

    function highlightName(name, tokens) {
        let out = escHtml(name);
        tokens.forEach((t) => {
            if (!t) return;
            out = out.replace(
                new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'),
                '<mark>$1</mark>'
            );
        });

        return out;
    }

    function paletteRow(item, tokens) {
        const a = document.createElement('a');
        a.className = 'cu-sr-row';
        a.setAttribute('href', item.url);
        a.innerHTML = '<span class="cu-sr-icon' + (item.kind === 'sub' ? ' sub' : '') + '">'
            + '<i class="bi ' + (item.kind === 'sub' ? 'bi-diagram-3' : 'bi-folder2') + '"></i></span>'
            + '<span class="cu-sr-name">' + highlightName(item.name, tokens) + '</span>'
            + (item.type ? '<span class="cu-tree-type">' + escHtml(item.type) + '</span>' : '')
            + '<span class="cu-sub-dot ' + dotFor(item.status) + '"></span>';

        return a;
    }

    function paletteRows() { return Array.from(paletteResults.querySelectorAll('.cu-sr-row')); }

    function setPaletteActive(i) {
        const rows = paletteRows();
        paletteActive = rows.length ? (((i % rows.length) + rows.length) % rows.length) : -1;
        rows.forEach((r, k) => r.classList.toggle('is-active', k === paletteActive));
        if (rows[paletteActive]) rows[paletteActive].scrollIntoView({ block: 'nearest' });
    }

    function renderPalette(q) {
        paletteResults.innerHTML = '';
        paletteActive = -1;
        const tokens = q.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        const dict = cuSearchModal.dataset;

        const addGroup = (label) => {
            const g = document.createElement('div');
            g.className = 'cu-sr-group';
            g.textContent = label;
            paletteResults.appendChild(g);
        };

        // Empty query: quick suggestions (root projects).
        if (!tokens.length) {
            const roots = paletteItems.filter((it) => it.kind === 'project').slice(0, 8);
            if (!roots.length) return;
            addGroup(dict.suggest || 'Suggestions');
            roots.forEach((it) => paletteResults.appendChild(paletteRow(it, [])));

            return;
        }

        const matched = [];
        paletteItems.forEach((it) => {
            const s = paletteScore(it, tokens);
            if (s >= 0) matched.push({ item: it, score: s });
        });
        matched.sort((a, b) => b.score - a.score || a.item.name.localeCompare(b.item.name));

        const pros = matched.filter((m) => m.item.kind === 'project').slice(0, 8);
        const subs = matched.filter((m) => m.item.kind === 'sub').slice(0, 24);

        if (!pros.length && !subs.length) {
            const d = document.createElement('div');
            d.className = 'cu-sr-empty';
            d.textContent = dict.empty || 'No matching projects.';
            paletteResults.appendChild(d);

            return;
        }
        if (pros.length) {
            addGroup(dict.grpProjects || 'Projects');
            pros.forEach((m) => paletteResults.appendChild(paletteRow(m.item, tokens)));
        }
        if (subs.length) {
            addGroup(dict.grpSubs || 'Sub-projects');
            subs.forEach((m) => paletteResults.appendChild(paletteRow(m.item, tokens)));
        }
        const hidden = matched.length - pros.length - subs.length;
        if (hidden > 0) {
            const d = document.createElement('div');
            d.className = 'cu-sr-more';
            d.textContent = '+' + hidden + ' ' + (dict.more || 'more — refine your search');
            paletteResults.appendChild(d);
        }
        setPaletteActive(0);
    }

    function openPalette() {
        paletteItems = buildPaletteIndex();
        paletteInput.value = searchInput.value || '';
        cuSearchModal.hidden = false;
        document.body.style.overflow = 'hidden';
        renderPalette(paletteInput.value);
        paletteInput.focus();
        paletteInput.select();
    }

    function closePalette() {
        if (cuSearchModal.hidden) return;
        cuSearchModal.hidden = true;
        if (!cuModal || cuModal.hidden) document.body.style.overflow = '';
    }

    if (searchBtn && cuSearchModal) {
        searchBtn.addEventListener('click', openPalette);

        // Typing also live-filters the grid behind the palette.
        paletteInput.addEventListener('input', () => {
            searchInput.value = paletteInput.value;
            searchInput.dispatchEvent(new Event('input'));
            renderPalette(paletteInput.value);
        });

        paletteResults.addEventListener('mousemove', (e) => {
            const row = e.target.closest('.cu-sr-row');
            if (!row) return;
            setPaletteActive(paletteRows().indexOf(row));
        });
        paletteResults.addEventListener('click', () => closePalette());

        paletteInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); setPaletteActive(paletteActive + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setPaletteActive(paletteActive - 1); }
            else if (e.key === 'Enter') {
                const rows = paletteRows();
                if (paletteActive >= 0 && rows[paletteActive]) {
                    e.preventDefault();
                    window.location.href = rows[paletteActive].getAttribute('href');
                }
            }
            else if (e.key === 'Escape') { e.preventDefault(); closePalette(); searchBtn.focus(); }
        });

        document.getElementById('cuSearchClose').addEventListener('click', closePalette);
        cuSearchModal.addEventListener('click', (e) => {
            if (e.target === cuSearchModal) closePalette();
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLocaleLowerCase() === 'k') {
                e.preventDefault();
                if (cuSearchModal.hidden) openPalette();
                else closePalette();
            }
            else if (e.key === 'Escape' && !cuSearchModal.hidden && document.activeElement !== paletteInput) {
                closePalette();
            }
        });
    }
});
</script>
@endpush
