<?php
/**
 * Ashna - Enterprise SaaS Admin Dashboard Layout Shell (Vector Icons + Bento Grid)
 *
 * Each tab panel is modularized under includes/admin/views/partials/tab-*.php
 *
 * @package Ashna
 * @var array $settings
 * @var array $sms_gateways
 * @var array $stats
 * @var array $chart_data
 * @var array $recent_logs
 * @var array $active_lockouts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_gw_obj    = isset( $sms_gateways[ $settings['active_sms_gateway'] ] ) ? $sms_gateways[ $settings['active_sms_gateway'] ] : reset( $sms_gateways );
$active_gw_title  = $active_gw_obj ? $active_gw_obj->get_title() : 'نامشخص';
$primary_down     = $active_gw_obj && 'sandbox' !== $active_gw_obj->get_id() && 'open' === Ashna_Failover::state( $active_gw_obj->get_id() );
$wc_active        = class_exists( 'WooCommerce' );
$elementor_active = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
$curl_active      = function_exists( 'curl_version' );
$openssl_active   = extension_loaded( 'openssl' );
$partials_dir     = ASHNA_OTP_PATH . 'includes/admin/views/partials/';
?>
<div class="ashna-app-shell" id="ashna-app-shell" dir="rtl">
	<!-- Floating Toast Notification -->
	<div id="ashna-toast" class="ashna-toast" style="display:none;">
		<span class="ashna-toast-icon"></span>
		<span class="ashna-toast-text"></span>
	</div>

	<style>
		#ashna-app-shell .ashna-theme-linear-icon { display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 20px !important; height: 20px !important; line-height: 0 !important; }
		#ashna-app-shell .ashna-icon-moon { display: block !important; width: 20px !important; height: 20px !important; flex-shrink: 0 !important; }
		#ashna-app-shell .ashna-icon-sun { display: none !important; width: 20px !important; height: 20px !important; flex-shrink: 0 !important; }
		#ashna-app-shell.is-dark .ashna-icon-moon { display: none !important; }
		#ashna-app-shell.is-dark .ashna-icon-sun { display: block !important; }

		/* Cache-Proof Dynamic Proportional Live Preview & Mini-Video Previews (v2.7.5) */
		#ashna-app-shell .ashna-studio-layout { grid-template-columns: minmax(0, 1fr) 430px !important; transition: grid-template-columns 0.25s ease; align-items: start !important; }
		#ashna-app-shell .ashna-studio-layout.is-split-preview-active { grid-template-columns: minmax(0, 1fr) 510px !important; }
		#ashna-app-shell .ashna-studio-layout { display: grid; grid-template-columns: minmax(0, 1fr) 440px; gap: 22px; align-items: start; transition: grid-template-columns 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
		#ashna-app-shell .ashna-studio-layout.is-split-preview-active { grid-template-columns: minmax(0, 1fr) 500px; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview { grid-template-columns: minmax(310px, 1fr) 680px !important; }
		@media (max-width: 1400px) {
			#ashna-app-shell .ashna-studio-layout.is-wide-preview { grid-template-columns: minmax(280px, 42%) 58% !important; }
		}
		#ashna-app-shell .ashna-studio-preview-col { position: sticky !important; top: 124px !important; align-self: start !important; z-index: 40; max-width: 100%; overflow: hidden; }
		@media (max-width: 1024px) {
			#ashna-app-shell .ashna-studio-layout, #ashna-app-shell .ashna-studio-layout.is-split-preview-active, #ashna-app-shell .ashna-studio-layout.is-wide-preview { grid-template-columns: 1fr !important; }
			#ashna-app-shell .ashna-studio-preview-col { position: static !important; top: auto !important; }
		}

		/* Exclusive Collapsible Accordion Cards in Appearance Studio */
		#ashna-app-shell .ashna-accordion-card { padding: 0 !important; overflow: hidden; transition: border-color 0.25s ease, box-shadow 0.25s ease; }
		#ashna-app-shell .ashna-accordion-card.is-open { border-color: var(--s-primary) !important; box-shadow: 0 10px 28px -8px rgba(37, 99, 235, 0.14); }
		#ashna-app-shell .ashna-accordion-card > .ashna-accordion-trigger { margin: 0 !important; padding: 20px 24px !important; border-bottom: 1px solid transparent !important; cursor: pointer !important; user-select: none; transition: background-color 0.2s ease, border-color 0.2s ease; }
		#ashna-app-shell .ashna-accordion-card > .ashna-accordion-trigger:hover { background-color: var(--s-bg-subtle); }
		#ashna-app-shell .ashna-accordion-card.is-open > .ashna-accordion-trigger { border-bottom-color: var(--s-border) !important; background-color: var(--s-bg-subtle); }
		#ashna-app-shell .ashna-accordion-chevron { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; background: var(--s-bg-surface); border: 1px solid var(--s-border-input); color: var(--s-text-muted); transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s ease, border-color 0.2s ease; flex-shrink: 0; }
		#ashna-app-shell .ashna-accordion-card.is-open .ashna-accordion-chevron { transform: rotate(180deg); color: var(--s-primary); border-color: var(--s-primary); background: var(--s-primary-soft); }
		#ashna-app-shell .ashna-accordion-body { padding: 22px 24px 24px 24px; }

		/* Ambient Glass Orbs Behind Live Preview Card (Makes Backdrop-Filter Blur Visibly Crisp) */
		#ashna-app-shell .ashna-preview-canvas { position: relative; overflow: hidden !important; box-sizing: border-box !important; max-width: 100% !important; min-height: 460px !important; padding: 34px 24px !important; background-color: #0f172a; justify-content: center; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview .ashna-preview-canvas:not(.is-modal-mode) { min-height: 510px !important; padding: 38px 30px !important; }
		#ashna-app-shell .ashna-prev-card { border: none !important; box-shadow: none !important; overflow: hidden !important; background-clip: padding-box !important; }
		#ashna-app-shell #ashna-live-preview-card input.ashna-prev-input { margin: 0 !important; box-sizing: border-box !important; }
		#ashna-app-shell #ashna-live-preview-card input.ashna-prev-input::placeholder { color: rgba(100, 116, 139, 0.78) !important; opacity: 1 !important; }
		#ashna-app-shell #ashna-live-preview-card.is-dark-preview-card input.ashna-prev-input::placeholder { color: rgba(203, 213, 225, 0.65) !important; opacity: 1 !important; }
		#ashna-app-shell .ashna-prev-glass-orbs { position: absolute; inset: 0; pointer-events: none; z-index: 0; opacity: 0.48; transition: opacity 0.35s ease; overflow: hidden; }
		#ashna-app-shell .ashna-prev-glass-orbs.is-glass-active { opacity: 0.92; }
		#ashna-app-shell .ashna-glass-orb { position: absolute; border-radius: 50%; filter: blur(8px); transition: background 0.35s ease; }
		#ashna-app-shell .ashna-glass-orb.orb-1 { width: 155px; height: 155px; background: #3b82f6; top: 14%; right: 10%; box-shadow: inset 0 0 24px rgba(255,255,255,0.45); }
		#ashna-app-shell .ashna-glass-orb.orb-2 { width: 140px; height: 140px; background: #ef4444; bottom: 12%; left: 10%; box-shadow: inset 0 0 24px rgba(255,255,255,0.45); }
		#ashna-app-shell .ashna-glass-orb.orb-3 { width: 95px; height: 95px; background: linear-gradient(135deg, #10b981, #f59e0b); top: 48%; left: 42%; transform: translate(-50%, -50%) rotate(25deg); border-radius: 24px; }

		#ashna-app-shell .ashna-preview-subbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 14px; background: var(--s-bg-surface); border-bottom: 1px solid var(--s-border-input); flex-wrap: wrap; }
		#ashna-app-shell .ashna-preview-mode-tabs { display: inline-flex; background: var(--s-bg-subtle); padding: 3px; border-radius: 8px; border: 1px solid var(--s-border-input); gap: 3px; }
		#ashna-app-shell .ashna-prev-mode-btn { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; border: none; background: transparent; color: var(--s-text-muted); font-family: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
		#ashna-app-shell .ashna-prev-mode-btn.active { background: var(--s-primary); color: #ffffff; }
		#ashna-app-shell .ashna-prev-expand-btn { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 7px; border: 1px solid var(--s-border-input); background: var(--s-bg-subtle); color: var(--s-text); font-family: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
		#ashna-app-shell .ashna-prev-expand-btn.active { border-color: var(--s-primary); color: var(--s-primary); background: var(--s-primary-soft); }
		#ashna-app-shell .ashna-preview-viewport { width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; overflow: visible !important; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.25s ease; position: relative; z-index: 2; }
		#ashna-app-shell #ashna-live-preview-shell { width: 100% !important; max-width: 350px !important; box-sizing: border-box !important; zoom: 1 !important; margin: 0 !important; overflow: visible !important; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview #ashna-live-preview-shell:not(.is-split) { max-width: 470px !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split { width: 100% !important; max-width: 100% !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-live-preview-card { flex: 0 0 55% !important; min-width: 0 !important; max-width: 55% !important; box-sizing: border-box !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-prev-split-banner { flex: 0 0 45% !important; min-width: 0 !important; max-width: 45% !important; box-sizing: border-box !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-prev-split-title { font-size: 13.5px !important; line-height: 1.45 !important; margin-bottom: 6px !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-prev-split-subtitle { font-size: 11px !important; line-height: 1.6 !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-prev-split-badge { font-size: 10px !important; padding: 3px 9px !important; margin-bottom: 8px !important; }
		#ashna-app-shell #ashna-live-preview-shell.is-split #ashna-prev-split-features { font-size: 10.5px !important; gap: 5px !important; margin-top: 12px !important; padding-top: 10px !important; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview #ashna-live-preview-shell.is-split #ashna-prev-split-title { font-size: 16.5px !important; margin-bottom: 10px !important; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview #ashna-live-preview-shell.is-split #ashna-prev-split-subtitle { font-size: 12.5px !important; line-height: 1.7 !important; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview #ashna-live-preview-shell.is-split #ashna-prev-split-badge { font-size: 11.5px !important; padding: 4px 12px !important; margin-bottom: 12px !important; }
		#ashna-app-shell .ashna-studio-layout.is-wide-preview #ashna-live-preview-shell.is-split #ashna-prev-split-features { font-size: 12px !important; gap: 7px !important; margin-top: 18px !important; padding-top: 14px !important; }

		/* Polished Category 4 Micro-Animations: Choreographed Entrance, Staggered Elements, Digit Pop & Elastic Error Shake */
		@keyframes ashnaAnimFadeUp {
			0% { opacity: 0; transform: translate3d(0, 22px, 0) scale(0.98); }
			100% { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
		}
		@keyframes ashnaAnimZoomSpring {
			0% { opacity: 0; transform: scale(0.82) translate3d(0, 10px, 0); }
			65% { opacity: 1; transform: scale(1.035) translate3d(0, -2px, 0); }
			85% { transform: scale(0.99) translate3d(0, 0, 0); }
			100% { opacity: 1; transform: scale(1) translate3d(0, 0, 0); }
		}
		@keyframes ashnaAnimSlideRtl {
			0% { opacity: 0; transform: translate3d(34px, 0, 0); }
			70% { opacity: 1; transform: translate3d(-4px, 0, 0); }
			100% { opacity: 1; transform: translate3d(0, 0, 0); }
		}
		@keyframes ashnaStaggerChildIn {
			0% { opacity: 0; transform: translate3d(0, 12px, 0); }
			100% { opacity: 1; transform: translate3d(0, 0, 0); }
		}
		@keyframes ashnaShakeKeyframes {
			0% { transform: translate3d(0, 0, 0) rotate(0deg); }
			12% { transform: translate3d(-11px, 0, 0) rotate(-0.8deg); }
			24% { transform: translate3d(10px, 0, 0) rotate(0.8deg); }
			36% { transform: translate3d(-8px, 0, 0) rotate(-0.5deg); }
			48% { transform: translate3d(7px, 0, 0) rotate(0.5deg); }
			60% { transform: translate3d(-5px, 0, 0) rotate(-0.3deg); }
			72% { transform: translate3d(4px, 0, 0) rotate(0.2deg); }
			84% { transform: translate3d(-2px, 0, 0) rotate(0deg); }
			100% { transform: translate3d(0, 0, 0) rotate(0deg); }
		}
		#ashna-app-shell .ashna-anim-fade_up { animation: ashnaAnimFadeUp 0.54s cubic-bezier(0.22, 1, 0.36, 1) both; }
		#ashna-app-shell .ashna-anim-zoom_spring { animation: ashnaAnimZoomSpring 0.62s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
		#ashna-app-shell .ashna-anim-slide_rtl { animation: ashnaAnimSlideRtl 0.52s cubic-bezier(0.22, 1, 0.36, 1) both; }
		#ashna-app-shell .ashna-stagger-run { animation: ashnaStaggerChildIn 0.46s cubic-bezier(0.22, 1, 0.36, 1) both; }
		#ashna-app-shell .ashna-shake-anim { animation: ashnaShakeKeyframes 0.64s cubic-bezier(0.36, 0.07, 0.19, 0.97) both; }
		#ashna-app-shell .ashna-prev-digit.is-error-digit {
			border-color: #ef4444 !important;
			color: #ef4444 !important;
			background-color: rgba(239, 68, 68, 0.14) !important;
			box-shadow: 0 0 0 3.5px rgba(239, 68, 68, 0.22), 0 6px 16px -4px rgba(239, 68, 68, 0.35) !important;
			transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
		}
		#ashna-app-shell .ashna-prev-error-toast {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 9px 12px;
			margin-bottom: 14px;
			border-radius: 10px;
			background: rgba(239, 68, 68, 0.14);
			border: 1px solid rgba(239, 68, 68, 0.42);
			color: #dc2626;
			font-size: 11.5px;
			font-weight: 700;
			line-height: 1.5;
			box-shadow: 0 6px 16px -4px rgba(239, 68, 68, 0.22);
		}
		#ashna-app-shell #ashna-live-preview-card.is-dark-preview-card .ashna-prev-error-toast {
			background: rgba(239, 68, 68, 0.22);
			border-color: rgba(248, 113, 113, 0.5);
			color: #fca5a5;
		}
		#ashna-app-shell .ashna-prev-error-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			color: #ef4444;
			flex-shrink: 0;
		}
		#ashna-app-shell #ashna-prev-btn-1:hover,
		#ashna-app-shell #ashna-prev-btn-2:hover {
			transform: translateY(-1.5px);
			filter: brightness(1.06);
		}
		#ashna-app-shell #ashna-prev-btn-1:active,
		#ashna-app-shell #ashna-prev-btn-2:active {
			transform: translateY(0.5px) scale(0.985);
		}

		/* Continuous Micro-Animations inside Choice Card Mini-Videos (.ashna-mini-video) */
		@keyframes ashnaMiniDigitFloat {
			0%, 100% { transform: translateY(-2px) scale(1.04); box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25); }
			50% { transform: translateY(1px) scale(0.98); box-shadow: 0 0 10px rgba(56, 189, 248, 0.55); }
		}
		@keyframes ashnaMiniProgressAnim {
			0% { width: 88%; }
			50% { width: 28%; }
			100% { width: 88%; }
		}
		@keyframes ashnaMiniRingAnim {
			0% { stroke-dashoffset: 8; }
			50% { stroke-dashoffset: 42; }
			100% { stroke-dashoffset: 8; }
		}
		@keyframes ashnaMiniClockPulse {
			0%, 100% { transform: scale(1); }
			50% { transform: scale(1.05); border-color: rgba(56, 189, 248, 0.75); }
		}
		@keyframes ashnaMvFadeUpLoop {
			0%, 15% { opacity: 0; transform: translateY(14px); }
			40%, 80% { opacity: 1; transform: translateY(0); }
			95%, 100% { opacity: 0; transform: translateY(-6px); }
		}
		@keyframes ashnaMvZoomSpringLoop {
			0%, 15% { opacity: 0; transform: scale(0.68); }
			38% { opacity: 1; transform: scale(1.08); }
			50%, 80% { opacity: 1; transform: scale(1); }
			95%, 100% { opacity: 0; transform: scale(0.9); }
		}
		@keyframes ashnaMvSlideRtlLoop {
			0%, 15% { opacity: 0; transform: translateX(22px); }
			38% { opacity: 1; transform: translateX(-3px); }
			50%, 80% { opacity: 1; transform: translateX(0); }
			95%, 100% { opacity: 0; transform: translateX(-14px); }
		}
		@keyframes ashnaMvErrShakeLoop {
			0%, 25%, 100% { transform: translateX(0); }
			30% { transform: translateX(-6px) rotate(-1deg); }
			36% { transform: translateX(6px) rotate(1deg); }
			42% { transform: translateX(-5px); }
			48% { transform: translateX(4px); }
			54% { transform: translateX(-2px); }
		}
		@keyframes ashnaMvErrBoxLoop {
			0%, 24%, 85%, 100% { background: rgba(30, 41, 59, 0.8); border-color: #475569; color: #e2e8f0; box-shadow: none; }
			30%, 72% { background: rgba(239, 68, 68, 0.22); border-color: #ef4444; color: #fca5a5; box-shadow: 0 0 8px rgba(239, 68, 68, 0.5); }
		}
		@keyframes ashnaMvErrPillLoop {
			0%, 24%, 85%, 100% { opacity: 0; transform: translateY(4px) scale(0.9); }
			32%, 72% { opacity: 1; transform: translateY(0) scale(1); }
		}
		#ashna-app-shell .ashna-mini-digit-active { animation: ashnaMiniDigitFloat 2.2s ease-in-out infinite; }
		#ashna-app-shell .ashna-mini-progress-fill { animation: ashnaMiniProgressAnim 3.2s ease-in-out infinite; }
		#ashna-app-shell .ashna-mini-ring-circle { animation: ashnaMiniRingAnim 3.2s ease-in-out infinite; }
		#ashna-app-shell .ashna-mini-clock-tick { animation: ashnaMiniClockPulse 2.4s ease-in-out infinite; }
		#ashna-app-shell .ashna-mv-card-anim { width: 76px; height: 48px; border-radius: 8px; background: rgba(255, 255, 255, 0.94); padding: 7px 9px; display: flex; flex-direction: column; justify-content: center; gap: 5px; box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35); }
		#ashna-app-shell .ashna-mv-card-anim.is-fade-up { animation: ashnaMvFadeUpLoop 2.8s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
		#ashna-app-shell .ashna-mv-card-anim.is-zoom-spring { animation: ashnaMvZoomSpringLoop 2.8s cubic-bezier(0.34, 1.56, 0.64, 1) infinite; }
		#ashna-app-shell .ashna-mv-card-anim.is-slide-rtl { animation: ashnaMvSlideRtlLoop 2.8s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
		#ashna-app-shell .ashna-mv-error-shake-demo { animation: ashnaMvErrShakeLoop 2.6s ease-in-out infinite; }
		#ashna-app-shell .ashna-mv-err-box { width: 20px; height: 23px; border-radius: 5px; border: 1.5px solid #475569; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; animation: ashnaMvErrBoxLoop 2.6s ease-in-out infinite; }
		#ashna-app-shell .ashna-mv-err-pill { font-size: 8.5px; font-weight: 800; padding: 1px 7px; border-radius: 99px; background: rgba(239, 68, 68, 0.25); border: 1px solid rgba(239, 68, 68, 0.55); color: #fca5a5; direction: rtl; animation: ashnaMvErrPillLoop 2.6s ease-in-out infinite; }

		/* Mini-Video Motion Previews */
		#ashna-app-shell .ashna-mini-video { width: 100%; height: 78px; border-radius: 10px; background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(148, 163, 184, 0.22); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; box-shadow: inset 0 1px 8px rgba(0, 0, 0, 0.35); }
		#ashna-app-shell .ashna-mini-video-badge { position: absolute; top: 6px; left: 7px; z-index: 3; display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 99px; background: rgba(15, 23, 42, 0.72); border: 1px solid rgba(255, 255, 255, 0.14); color: #93c5fd; font-size: 9.5px; font-weight: 700; }
		#ashna-app-shell .ashna-mini-video-badge::before { content: ""; width: 5px; height: 5px; border-radius: 50%; background: #38bdf8; box-shadow: 0 0 6px #38bdf8; }
		#ashna-app-shell .ashna-mv-card { width: 54px; height: 54px; border-radius: 7px; background: #ffffff; padding: 6px; display: flex; flex-direction: column; justify-content: center; gap: 4px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35); }
		#ashna-app-shell .ashna-mv-line { height: 4px; border-radius: 3px; background: #cbd5e1; width: 75%; margin: 0 auto; }
		#ashna-app-shell .ashna-mv-input { height: 8px; border-radius: 3px; background: #f1f5f9; border: 1px solid #94a3b8; width: 100%; position: relative; overflow: hidden; }
		#ashna-app-shell .ashna-mv-btn { height: 8px; border-radius: 3px; background: linear-gradient(90deg, #2563eb, #4f46e5); width: 100%; }
		#ashna-app-shell .ashna-mv-split { width: 108px; height: 54px; border-radius: 7px; overflow: hidden; display: flex; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35); }
		#ashna-app-shell .ashna-mv-split.is-reverse { flex-direction: row-reverse; }
		#ashna-app-shell .ashna-mv-split .ashna-mv-card { width: 54px; border-radius: 0; box-shadow: none; }
		#ashna-app-shell .ashna-mv-banner { width: 54px; height: 54px; background: linear-gradient(135deg, #1e3a8a, #3b82f6); padding: 6px; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
		#ashna-app-shell .ashna-mv-banner span { display: block; height: 3.5px; border-radius: 2px; background: rgba(255, 255, 255, 0.78); }
		#ashna-app-shell .ashna-mv-pos-track { width: 124px; height: 54px; border-radius: 7px; border: 1px dashed rgba(148, 163, 184, 0.35); padding: 4px; display: flex; align-items: center; }
		#ashna-app-shell .ashna-mv-pos-track.is-center { justify-content: center; }
		#ashna-app-shell .ashna-mv-pos-track.is-right { justify-content: flex-start; }
		#ashna-app-shell .ashna-mv-pos-track.is-left { justify-content: flex-end; }
		#ashna-app-shell .ashna-mv-screen { width: 126px; height: 60px; border-radius: 7px; background: #1e293b; border: 1px solid rgba(148, 163, 184, 0.32); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
		#ashna-app-shell .ashna-mv-screen-bg { position: absolute; inset: 6px; display: flex; flex-direction: column; gap: 4px; opacity: 0.25; }
		#ashna-app-shell .ashna-mv-screen-bg i { display: block; height: 5px; border-radius: 3px; background: #94a3b8; }
		#ashna-app-shell .ashna-mv-modal-center { width: 48px; height: 40px; border-radius: 6px; background: #ffffff; padding: 5px; display: flex; flex-direction: column; justify-content: center; gap: 3px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.5); animation: ashnaInlinePopupLoop 2.8s infinite cubic-bezier(0.16, 1, 0.3, 1); }
		#ashna-app-shell .ashna-mv-drawer-left { position: absolute; left: 0; top: 0; bottom: 0; width: 44px; background: #ffffff; border-radius: 0 6px 6px 0; padding: 6px 5px; display: flex; flex-direction: column; justify-content: center; gap: 3px; box-shadow: 4px 0 14px rgba(0, 0, 0, 0.45); animation: ashnaInlineDrawerLeftLoop 2.8s infinite cubic-bezier(0.16, 1, 0.3, 1); }
		#ashna-app-shell .ashna-mv-drawer-right { position: absolute; right: 0; top: 0; bottom: 0; width: 44px; background: #ffffff; border-radius: 6px 0 0 6px; padding: 6px 5px; display: flex; flex-direction: column; justify-content: center; gap: 3px; box-shadow: -4px 0 14px rgba(0, 0, 0, 0.45); animation: ashnaInlineDrawerRightLoop 2.8s infinite cubic-bezier(0.16, 1, 0.3, 1); }
		#ashna-app-shell .ashna-mv-bottom-sheet { position: absolute; left: 14px; right: 14px; bottom: 0; height: 38px; background: #ffffff; border-radius: 8px 8px 0 0; padding: 5px 8px; display: flex; flex-direction: column; justify-content: center; gap: 3px; box-shadow: 0 -4px 14px rgba(0, 0, 0, 0.45); animation: ashnaInlineBottomSheetLoop 2.8s infinite cubic-bezier(0.16, 1, 0.3, 1); }
		#ashna-app-shell .ashna-mv-flow-stage { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 0 12px; }
		#ashna-app-shell .ashna-mv-track { width: 44px; height: 3px; background: rgba(148, 163, 184, 0.28); border-radius: 99px; position: relative; overflow: hidden; }
		#ashna-app-shell .ashna-mv-packet { position: absolute; top: 0; right: -14px; width: 16px; height: 3px; border-radius: 99px; background: #38bdf8; box-shadow: 0 0 8px #38bdf8; animation: ashnaInlinePacket 1.8s infinite linear; }
		#ashna-app-shell .ashna-mv-packet.is-green { background: #10b981; box-shadow: 0 0 8px #10b981; }
		@keyframes ashnaInlinePacket { 0% { right: -16px; } 100% { right: 100%; } }
		@keyframes ashnaInlinePopupLoop { 0%, 12% { opacity: 0; transform: scale(0.68) translateY(8px); } 28%, 80% { opacity: 1; transform: scale(1) translateY(0); } 92%, 100% { opacity: 0; transform: scale(0.85); } }
		@keyframes ashnaInlineDrawerLeftLoop { 0%, 12% { transform: translateX(-100%); } 28%, 80% { transform: translateX(0); } 92%, 100% { transform: translateX(-100%); } }
		@keyframes ashnaInlineDrawerRightLoop { 0%, 12% { transform: translateX(100%); } 28%, 80% { transform: translateX(0); } 92%, 100% { transform: translateX(100%); } }
		@keyframes ashnaInlineBottomSheetLoop { 0%, 12% { transform: translateY(100%); } 28%, 80% { transform: translateY(0); } 92%, 100% { transform: translateY(100%); } }
	</style>
	<form id="ashna-settings-form" method="post" action="" novalidate="novalidate">
		<?php wp_nonce_field( 'ashna_save_settings_action', 'ashna_settings_nonce' ); ?>
		<input type="hidden" name="ashna_save_settings" value="1" />

		<!-- STICKY TOPBAR -->
		<header class="ashna-topbar">
			<div class="ashna-topbar-brand">
				<div class="ashna-brand-logo">
					<img src="<?php echo esc_url( ASHNA_OTP_URL . 'assets/images/ashna-icon.svg' ); ?>" width="40" height="40" alt="" />
				</div>
				<div>
					<div class="ashna-brand-title-row">
						<h1>آشنا</h1>
						<span class="ashna-badge-ver">v<?php echo esc_html( ASHNA_OTP_VERSION ); ?> Pro</span>
					</div>
					<p>ورود و عضویت سریع با پیامک، بله و ایمیل</p>
				</div>
			</div>

			<div class="ashna-topbar-actions">
				<div class="ashna-gateway-status-pill" title="<?php echo esc_attr( $primary_down ? 'درگاه اصلی موقتاً کنار گذاشته شده؛ کدها از پشتیبان ارسال می‌شوند' : 'درگاه پیامک فعال فعلی' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0;display:block;opacity:0.8;"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
					<span class="ashna-status-dot <?php echo esc_attr( 'sandbox' === $settings['active_sms_gateway'] ? 'is-warning' : ( $primary_down ? 'is-offline' : 'is-online' ) ); ?>"></span>
					<span>درگاه فعال: <strong id="ashna-topbar-gw-name"><?php echo esc_html( $active_gw_title ); ?></strong></span>
				</div>

				<button type="button" id="ashna-theme-toggle" class="ashna-icon-btn" title="تغییر حالت روشن / تاریک (Dark Mode)" style="padding:0 !important;width:40px !important;height:40px !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;">
					<span class="ashna-theme-linear-icon">
						<svg class="ashna-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
						<svg class="ashna-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
					</span>
				</button>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ashna-otp-logs' ) ); ?>" class="ashna-btn-secondary">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
					<span>گزارش کدها (<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>)</span>
				</a>

				<button type="button" id="ashna-ajax-save-btn" class="ashna-btn-save ashna-save-trigger-btn" onclick="return window.ashnaSaveSettingsNow ? window.ashnaSaveSettingsNow(event, this) : true;">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0;display:block;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
					<span class="ashna-save-label">ذخیره تغییرات</span>
					<span id="ashna-unsaved-dot" class="ashna-unsaved-dot" style="display:none;" title="تغییرات ذخیره نشده"></span>
					<kbd class="ashna-kbd">Ctrl+S</kbd>
				</button>
			</div>
		</header>

		<!-- APP BODY (ORGANIZED SIDEBAR + MAIN PANEL) -->
		<div class="ashna-app-body">
			<!-- CATEGORIZED VERTICAL SIDEBAR WITH VECTOR ICONS -->
			<aside class="ashna-sidebar">
				<nav class="ashna-nav">
					<div class="ashna-sidebar-group-title">گزارش و وضعیت</div>
					<button type="button" class="ashna-nav-item active" data-tab="dashboard">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
						<span class="ashna-nav-label">پیشخوان</span>
					</button>

					<div class="ashna-sidebar-group-title">مسیر ورود و ارسال</div>
					<button type="button" class="ashna-nav-item" data-tab="auth_flow">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
						<span class="ashna-nav-label">روش و مسیر ورود</span>
					</button>

					<button type="button" class="ashna-nav-item" data-tab="sms_gateways">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
						<span class="ashna-nav-label">درگاه پیامک</span>
					</button>

					<button type="button" class="ashna-nav-item" data-tab="bale_email">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/></svg>
						<span class="ashna-nav-label">پیام‌رسان‌ها و ایمیل</span>
					</button>

					<div class="ashna-sidebar-group-title">ظاهر و اتصال به قالب</div>
					<button type="button" class="ashna-nav-item" data-tab="appearance_studio">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
						<span class="ashna-nav-label">طراحی ظاهر فرم</span>
					</button>

					<button type="button" class="ashna-nav-item" data-tab="woocommerce">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
						<span class="ashna-nav-label">ووکامرس و یکپارچگی‌ها</span>
					</button>

					<div class="ashna-sidebar-group-title">امنیت و ابزارها</div>
					<button type="button" class="ashna-nav-item" data-tab="security_firewall">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
						<span class="ashna-nav-label">امنیت و ضد اسپم</span>
						<?php if ( count( $active_lockouts ) > 0 ) : ?>
							<span class="ashna-nav-counter"><?php echo esc_html( (string) count( $active_lockouts ) ); ?></span>
						<?php endif; ?>
					</button>

					<button type="button" class="ashna-nav-item" data-tab="tools_backup">
						<svg class="ashna-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
						<span class="ashna-nav-label">تست و پشتیبان‌گیری</span>
					</button>
				</nav>

				<div class="ashna-sidebar-footer">
					<p>آشنا v<?php echo esc_html( ASHNA_OTP_VERSION ); ?></p>
					<small class="ashna-credit">محصولی از <a href="https://parsena.ir" target="_blank" rel="noopener">پارسنا<span class="screen-reader-text"> (در زبانه‌ی جدید باز می‌شود)</span></a></small>
				</div>
			</aside>

			<!-- MAIN CONTENT PANELS (Modular Partials) -->
			<main class="ashna-main">

				<?php
				require $partials_dir . 'tab-dashboard.php';
				require $partials_dir . 'tab-auth-flow.php';
				require $partials_dir . 'tab-sms-gateways.php';
				require $partials_dir . 'tab-bale-email.php';
				require $partials_dir . 'tab-appearance.php';
				require $partials_dir . 'tab-woocommerce.php';
				require $partials_dir . 'tab-security.php';
				require $partials_dir . 'tab-tools.php';
				?>

				<!-- Bottom Action Footer Bar -->
				<div class="ashna-bottom-save-bar" style="margin-top:22px;padding:16px 22px;border-radius:14px;background:var(--s-bg-surface);border:1px solid var(--s-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
					<div style="display:flex;align-items:center;gap:10px;color:var(--s-text-muted);font-size:13px;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:0.8;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						<span>پس از تغییر هر بخش، دکمه تایید و ذخیره تنظیمات را بزنید (یا کلید میانبر <code>Ctrl + S</code>).</span>
					</div>
					<button type="button" id="ashna-ajax-save-btn-bottom" class="ashna-btn-save ashna-save-trigger-btn" onclick="return window.ashnaSaveSettingsNow ? window.ashnaSaveSettingsNow(event, this) : true;">
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0;display:block;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
						<span class="ashna-save-label">تایید و ذخیره تنظیمات</span>
					</button>
				</div>
			</main>
		</div>
	</form>

	<script>
	(function() {
		var isSaving = false;
		var ajaxEndpoint = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var adminNonce   = <?php echo wp_json_encode( wp_create_nonce( 'ashna_admin_nonce' ) ); ?>;

		function parseJsonResilient(rawText) {
			if (!rawText || typeof rawText !== 'string') {
				return null;
			}
			var trimmed = rawText.trim();
			try {
				return JSON.parse(trimmed);
			} catch (e) {}
			var idx = trimmed.indexOf('{"success":');
			if (idx !== -1) {
				var lastBrace = trimmed.lastIndexOf('}');
				if (lastBrace > idx) {
					try {
						return JSON.parse(trimmed.substring(idx, lastBrace + 1));
					} catch (e2) {}
				}
			}
			return null;
		}

		function showFeedback(message, isError) {
			var toast = document.getElementById('ashna-toast');
			if (toast) {
				toast.className = 'ashna-toast' + (isError ? ' is-error' : '');
				var iconEl = toast.querySelector('.ashna-toast-icon');
				var textEl = toast.querySelector('.ashna-toast-text');
				if (iconEl) {
					iconEl.innerHTML = isError
						? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
						: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
				}
				if (textEl) {
					textEl.textContent = message;
				}
				toast.style.display = 'flex';
				setTimeout(function() {
					toast.style.display = 'none';
				}, 3000);
			}
		}

		function setButtonsState(saving, doneSuccess) {
			var btns = document.querySelectorAll('.ashna-save-trigger-btn, #ashna-ajax-save-btn');
			for (var i = 0; i < btns.length; i++) {
				btns[i].disabled = !!saving;
				var lbl = btns[i].querySelector('.ashna-save-label');
				if (lbl) {
					if (saving) {
						lbl.textContent = 'در حال ذخیره...';
					} else if (doneSuccess) {
						lbl.textContent = 'ذخیره شد ✓';
					} else {
						lbl.textContent = btns[i].id === 'ashna-ajax-save-btn-bottom' ? 'تایید و ذخیره تنظیمات' : 'ذخیره تغییرات';
					}
				}
				if (doneSuccess) {
					btns[i].classList.remove('has-unsaved');
				}
			}
			if (doneSuccess) {
				var dot = document.getElementById('ashna-unsaved-dot');
				if (dot) {
					dot.style.display = 'none';
				}
				setTimeout(function() {
					setButtonsState(false, false);
				}, 1800);
			}
		}

		function fallbackClassicSubmit(form) {
			try {
				HTMLFormElement.prototype.submit.call(form);
			} catch (err) {
				form.submit();
			}
		}

		window.ashnaSaveSettingsNow = function(evt, btnEl) {
			if (evt) {
				if (typeof evt.preventDefault === 'function') {
					evt.preventDefault();
				}
				if (typeof evt.stopImmediatePropagation === 'function') {
					evt.stopImmediatePropagation();
				}
			}
			if (isSaving) {
				return false;
			}
			var form = document.getElementById('ashna-settings-form');
			if (!form) {
				return false;
			}

			isSaving = true;
			setButtonsState(true, false);

			try {
				var fd = new FormData(form);
				fd.delete('ashna_save_settings');
				fd.append('action', 'ashna_admin_save_settings');
				fd.append('nonce', adminNonce);

				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxEndpoint, true);
				xhr.timeout = 20000;

				xhr.onload = function() {
					isSaving = false;
					if (xhr.status >= 200 && xhr.status < 300) {
						var parsed = parseJsonResilient(xhr.responseText);
						if (parsed && parsed.success) {
							setButtonsState(false, true);
							var msg = (parsed.data && parsed.data.message) ? parsed.data.message : 'تنظیمات با موفقیت ذخیره شد!';
							showFeedback(msg, false);
							var expBox = document.getElementById('ashna_export_json_box');
							if (expBox && parsed.data && parsed.data.settings) {
								expBox.value = JSON.stringify(parsed.data.settings);
							}
							return;
						}
					}
					// Automatic fallback to native POST submission if AJAX failed or returned error
					fallbackClassicSubmit(form);
				};

				xhr.onerror = function() {
					isSaving = false;
					fallbackClassicSubmit(form);
				};

				xhr.ontimeout = function() {
					isSaving = false;
					fallbackClassicSubmit(form);
				};

				xhr.send(fd);
			} catch (ex) {
				isSaving = false;
				fallbackClassicSubmit(form);
			}

			return false;
		};
	})();
	</script>
</div>
