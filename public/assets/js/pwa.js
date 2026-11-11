// assets/js/pwa.js - ระบบจัดการ Progressive Web App (PWA) รองรับทั้ง Android และ iOS
(function () {
    'use strict';

    // 1. ลงทะเบียน Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register('/sw.js', { scope: '/' })
                .then(function (reg) {
                    reg.update();
                })
                .catch(function (err) {
                    console.warn('Service Worker registration failed:', err);
                });
        });
    }

    // 2. ตรวจสอบสถานะการติดตั้ง (Standalone Mode) และแพลตฟอร์ม (iOS / Android)
    function isStandalone() {
        return (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: window-controls-overlay)').matches ||
            window.navigator.standalone === true
        );
    }

    function isIOS() {
        var ua = window.navigator.userAgent || '';
        var iOSDevice = /iPad|iPhone|iPod/.test(ua);
        var iPadOS = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        return iOSDevice || iPadOS;
    }

    function isAndroid() {
        return /Android/i.test(window.navigator.userAgent || '');
    }

    function isInAppBrowser() {
        var ua = window.navigator.userAgent || '';
        return /Line\/|FBAN|FBAV|Instagram/i.test(ua);
    }

    var deferredPrompt = null;

    // สร้างแถบแจ้งเตือนติดตั้งแอปลอยด้านล่างหน้าจอมือถือ (Smart Floating Banner สำหรับ iPhone & Android)
    function ensureFloatingBanner() {
        if (isStandalone() || document.getElementById('pwaFloatingBanner')) {
            return;
        }
        if (sessionStorage.getItem('pwa_banner_dismissed') === '1') {
            return;
        }

        var banner = document.createElement('div');
        banner.id = 'pwaFloatingBanner';
        banner.className = 'pwa-install-banner';
        banner.style.cssText = 'position:fixed;left:12px;right:12px;bottom:14px;z-index:1045;background:linear-gradient(135deg,#0b3c6d 0%,#16508b 100%);color:#fff;border-radius:16px;padding:12px 14px;box-shadow:0 10px 30px rgba(0,0,0,0.35);border:1.5px solid #c59b27;display:flex;align-items:center;justify-content:space-between;gap:10px;max-width:520px;margin:0 auto;';

        var subText = isIOS()
            ? 'สำหรับ iPhone: แตะเพื่อดูวิธีเพิ่มแอปลงหน้าจอโฮม'
            : 'ติดตั้งลงจอมือถือ เข้าใช้งานได้ทันทีไม่ต้องผ่านเว็บ';

        banner.innerHTML = `
            <div class="d-flex align-items-center gap-2 pwa-install-trigger" style="cursor:pointer;flex:1;min-width:0;">
                <img src="assets/icons/icon.svg" alt="App Icon" style="width:44px;height:44px;border-radius:10px;border:1.5px solid #c59b27;flex-shrink:0;background:#0b3c6d;">
                <div style="min-width:0;">
                    <div class="fw-bold text-warning" style="font-size:0.9rem;line-height:1.2;">แอปจองรถ FMS PNU (v2.0)</div>
                    <div class="text-light opacity-90 text-truncate" style="font-size:0.76rem;">${subText}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark px-3 py-1 pwa-install-trigger" style="border-radius:8px;font-size:0.82rem;">
                    <i class="fas fa-download me-1"></i>ติดตั้ง
                </button>
                <button type="button" id="pwaBannerCloseBtn" class="btn btn-sm text-light opacity-75 px-1" title="ปิด">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(banner);

        var closeBtn = document.getElementById('pwaBannerCloseBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                banner.remove();
                sessionStorage.setItem('pwa_banner_dismissed', '1');
            });
        }
    }

    // 3. สร้าง Modal แนะนำการติดตั้ง PWA (ทั้ง iOS และ Android)
    function ensureInstallModal() {
        if (document.getElementById('pwaInstallModal')) {
            return;
        }
        var modalHtml = `
        <div class="modal fade" id="pwaInstallModal" tabindex="-1" aria-labelledby="pwaInstallModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                    <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #0b3c6d 0%, #1e5799 100%);">
                        <div class="d-flex align-items-center gap-2">
                            <img src="assets/icons/icon.svg" alt="PWA Icon" style="width: 38px; height: 38px; border-radius: 9px; border: 1.5px solid #c59b27;">
                            <div>
                                <h6 class="modal-title fw-bold mb-0" id="pwaInstallModalLabel">ติดตั้งแอปลงบนหน้าจอมือถือ / คอมพิวเตอร์</h6>
                                <small class="text-light opacity-75" style="font-size: 0.75rem;">ระบบขออนุญาตใช้รถยนต์ (v2.0) - คณะวิทยาการจัดการ มนร.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- แจ้งเตือนกรณีเปิดผ่าน LINE หรือ Facebook ในมือถือ -->
                        <div id="pwaInAppWarning" class="alert alert-warning small mb-3 d-none" style="border-radius: 12px;">
                            <i class="fas fa-triangle-exclamation me-1 text-danger"></i>
                            <strong>คำแนะนำสำคัญ:</strong> ขณะนี้ท่านกำลังเปิดลิงก์ผ่านแอปแชท (เช่น LINE/Facebook) กรุณากดปุ่มแชร์มุมขวาบน/ล่าง แล้วเลือก <strong>"เปิดใน Safari"</strong> (สำหรับ iPhone) หรือ <strong>"เปิดในเบราว์เซอร์เริ่มต้น/Chrome"</strong> ก่อนติดตั้งแอปครับ
                        </div>
                        <!-- ส่วนแนะนำสำหรับ iOS (iPhone / iPad) -->
                        <div id="pwaGuideIOS" class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark px-2 py-1"><i class="fab fa-apple me-1"></i> สำหรับ iPhone / iPad (iOS)</span>
                                <span class="text-muted small">ผ่านเบราว์เซอร์ Safari</span>
                            </div>
                            <div class="p-3 rounded-3 mb-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 28px; height: 28px; background-color: #0b3c6d; font-size: 0.85rem;">1</div>
                                    <div class="small">
                                        แตะปุ่ม <strong>แชร์ (Share)</strong> ที่แถบเมนูด้านล่างของเบราว์เซอร์ Safari
                                        <span class="d-inline-flex align-items-center justify-content-center bg-white border rounded px-1 py-0 ms-1 shadow-sm" style="vertical-align: middle;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                                <polyline points="16 6 12 2 8 6"/>
                                                <line x1="12" y1="2" x2="12" y2="15"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 28px; height: 28px; background-color: #0b3c6d; font-size: 0.85rem;">2</div>
                                    <div class="small">
                                        เลื่อนลงมาแล้วเลือกเมนู <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</strong>
                                        <span class="d-inline-flex align-items-center justify-content-center bg-white border rounded px-1 py-0 ms-1 shadow-sm" style="vertical-align: middle;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0b3c6d" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="4" ry="4"/>
                                                <line x1="12" y1="8" x2="12" y2="16"/>
                                                <line x1="8" y1="12" x2="16" y2="12"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 28px; height: 28px; background-color: #0b3c6d; font-size: 0.85rem;">3</div>
                                    <div class="small">
                                        แตะปุ่ม <strong>"เพิ่ม" (Add)</strong> ที่มุมขวาบน แอปพร้อมไอคอน <strong>จองรถ FMS PNU</strong> จะปรากฏบนหน้าจอโฮมทันที
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ส่วนแนะนำสำหรับ Android / Chrome -->
                        <div id="pwaGuideAndroid">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success px-2 py-1"><i class="fab fa-android me-1"></i> สำหรับ Android / Chrome / Edge</span>
                            </div>
                            <div class="p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                <div id="pwaDirectInstallWrap" class="mb-3 d-none">
                                    <button type="button" id="pwaModalDirectInstallBtn" class="btn btn-pnu w-100 py-2 fw-bold shadow-sm">
                                        <i class="fas fa-download me-1"></i> คลิกที่นี่เพื่อติดตั้งแอปทันที
                                    </button>
                                </div>
                                <div class="d-flex align-items-start gap-3 mb-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 28px; height: 28px; background-color: #198754; font-size: 0.85rem;">1</div>
                                    <div class="small">
                                        แตะปุ่มเมนู <strong>จุดสามจุด (⋮)</strong> ที่มุมขวาบนของเบราว์เซอร์ Chrome
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 28px; height: 28px; background-color: #198754; font-size: 0.85rem;">2</div>
                                    <div class="small">
                                        เลือก <strong>"ติดตั้งแอป" (Install app)</strong> หรือ <strong>"เพิ่มลงในหน้าจอหลัก" (Add to Home screen)</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size: 0.78rem;">
                            <i class="fas fa-bolt text-warning me-1"></i> เข้าใช้งานรวดเร็ว เต็มหน้าจอ ไม่ต้องพิมพ์ URL
                        </span>
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                    </div>
                </div>
            </div>
        </div>`;

        var wrapper = document.createElement('div');
        wrapper.innerHTML = modalHtml;
        document.body.appendChild(wrapper.firstElementChild);

        var directBtn = document.getElementById('pwaModalDirectInstallBtn');
        if (directBtn) {
            directBtn.addEventListener('click', function () {
                triggerNativeInstall();
            });
        }
    }

    function openInstallModal() {
        ensureInstallModal();
        var iosSection = document.getElementById('pwaGuideIOS');
        var androidSection = document.getElementById('pwaGuideAndroid');
        var directWrap = document.getElementById('pwaDirectInstallWrap');
        var inAppWarn = document.getElementById('pwaInAppWarning');

        if (inAppWarn) {
            if (isInAppBrowser()) {
                inAppWarn.classList.remove('d-none');
            } else {
                inAppWarn.classList.add('d-none');
            }
        }

        if (directWrap) {
            if (deferredPrompt) {
                directWrap.classList.remove('d-none');
            } else {
                directWrap.classList.add('d-none');
            }
        }

        // จัดลำดับแสดงผลตามอุปกรณ์ที่กำลังใช้งาน
        if (iosSection && androidSection && iosSection.parentNode) {
            if (isIOS()) {
                iosSection.parentNode.insertBefore(iosSection, androidSection);
            } else if (isAndroid()) {
                androidSection.parentNode.insertBefore(androidSection, iosSection);
            }
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            var modalEl = document.getElementById('pwaInstallModal');
            var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function triggerNativeInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choiceResult) {
                if (choiceResult.outcome === 'accepted') {
                    hideInstallButtons();
                }
                deferredPrompt = null;
            });
        } else {
            openInstallModal();
        }
    }

    function hideInstallButtons() {
        var btns = document.querySelectorAll('.pwa-install-trigger');
        btns.forEach(function (btn) {
            btn.classList.add('d-none');
        });
        var banner = document.getElementById('pwaFloatingBanner');
        if (banner) {
            banner.remove();
        }
    }

    function showInstallButtons() {
        if (isStandalone()) {
            hideInstallButtons();
            return;
        }
        var btns = document.querySelectorAll('.pwa-install-trigger');
        btns.forEach(function (btn) {
            btn.classList.remove('d-none');
        });
        ensureFloatingBanner();
    }

    // 4. ดักจับเหตุการณ์ beforeinstallprompt บน Android / Chrome / Edge
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showInstallButtons();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        hideInstallButtons();
    });

    // 5. ผูก Event กับปุ่มติดตั้งแอปทุกจุดในหน้าเว็บ
    document.addEventListener('DOMContentLoaded', function () {
        if (isStandalone()) {
            document.documentElement.classList.add('pwa-standalone');
            hideInstallButtons();
        } else {
            showInstallButtons();
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.pwa-install-trigger');
            if (trigger) {
                e.preventDefault();
                if (deferredPrompt && !isIOS()) {
                    triggerNativeInstall();
                } else {
                    openInstallModal();
                }
            }
        });
    });
})();
