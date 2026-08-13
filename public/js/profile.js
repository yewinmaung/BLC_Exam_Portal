/**
 * profile.js — Profile page: photo cropper + password change + photo removal
 *
 * Reads window.PROFILE_CONFIG (set inline in profile/show.blade.php):
 *   photoUrl, passwordUrl, csrf, hasPhoto
 *
 * Dependencies: Bootstrap 5 (bundled), Bootstrap Icons (CSS only)
 * No external cropper library — pure Canvas API.
 */
(function () {
    'use strict';

    const C    = window.PROFILE_CONFIG || {};
    const csrf = C.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // DELETE photo URL: same path as POST photo but with DELETE method.
    // Derived from photoUrl so no extra Blade config is needed.
    const DELETE_PHOTO_URL = (C.photoUrl || '/profile/photo').replace(/\/$/, '');

    /* ════════════════════════════════════════════════════════════════
       Helpers
    ════════════════════════════════════════════════════════════════ */
    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function showMsg(el, type, text) {
        if (!el) return;
        el.innerHTML = `<div class="inline-alert ${type}"><i class="bi bi-${
            type === 'success' ? 'check-circle-fill' :
            type === 'error'   ? 'exclamation-triangle-fill' :
                                 'info-circle-fill'
        }"></i><span>${esc(text)}</span></div>`;
    }

    function clearMsg(el) {
        if (el) el.innerHTML = '';
    }

    async function apiFetch(url, body, method) {
        const r = await fetch(url, {
            method:  method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept':       'application/json',
            },
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
        return { ok: r.ok, status: r.status, data: await r.json() };
    }

    /* ════════════════════════════════════════════════════════════════
       Photo Cropper
    ════════════════════════════════════════════════════════════════ */

    // DOM refs
    const photoInput     = document.getElementById('photoFileInput');
    const avatarEditBtn  = document.getElementById('avatarEditBtn');
    const avatarImg      = document.getElementById('avatarImg');
    const avatarInitial  = document.getElementById('avatarInitial');
    const photoStatus    = document.getElementById('photoStatus');

    // Cropper modal
    const cropperOverlay = document.getElementById('cropperOverlay');
    const cropperStage   = document.getElementById('cropperStage');
    const cropperImg     = document.getElementById('cropperImg');
    const zoomSlider     = document.getElementById('zoomSlider');
    const btnCropCancel  = document.getElementById('btnCropCancel');
    const btnCropSave    = document.getElementById('btnCropSave');
    const cropperMsg     = document.getElementById('cropperMsg');

    // Cropper internal state
    let imgNatW = 0, imgNatH = 0;   // natural image size
    let scale   = 1;                // current zoom
    let offsetX = 0, offsetY = 0;   // image position (top-left of img relative to stage)
    let isDragging = false;
    let dragStartX = 0, dragStartY = 0;
    let dragStartOX = 0, dragStartOY = 0;

    // ── Track whether user currently has a photo ──────────────────────────
    // Initialised from Blade config; kept in sync by upload/remove actions.
    let currentlyHasPhoto = !!C.hasPhoto;

    // ── "Remove Photo" button — injected by JS below the camera button ───
    // Shown only when the user currently has a photo.
    const removeBtn = document.createElement('button');
    removeBtn.type      = 'button';
    removeBtn.id        = 'avatarRemoveBtn';
    removeBtn.title     = 'Remove photo';
    removeBtn.innerHTML = '<i class="bi bi-trash3-fill"></i>';
    removeBtn.style.cssText = [
        'position:absolute',
        'bottom:4px',
        'left:4px',
        'width:32px',
        'height:32px',
        'border-radius:50%',
        'background:#dc2626',
        'color:#fff',
        'border:2px solid #fff',
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'cursor:pointer',
        'font-size:0.8rem',
        'transition:background 0.15s',
    ].join(';');

    // Insert next to the camera button inside .avatar-wrap
    const avatarWrap = document.getElementById('avatarWrap');
    if (avatarWrap) avatarWrap.appendChild(removeBtn);

    // Show/hide the remove button based on whether a photo is set
    function syncRemoveBtn() {
        removeBtn.style.display = currentlyHasPhoto ? 'flex' : 'none';
    }
    syncRemoveBtn();

    // ── Helpers: switch main avatar between photo and initial ────────────

    function showPhoto(url) {
        if (avatarInitial) avatarInitial.style.display = 'none';
        avatarImg.src           = url;
        avatarImg.style.display = 'block';
        currentlyHasPhoto       = true;
        syncRemoveBtn();
    }

    function showInitial(letter) {
        // Hide the photo img
        avatarImg.style.display = 'none';
        avatarImg.src           = '';

        // Show or restore the initial span
        if (avatarInitial) {
            if (letter) avatarInitial.textContent = letter;
            avatarInitial.style.display = '';  // revert to default (inline)
        }

        currentlyHasPhoto = false;
        syncRemoveBtn();
    }

    // ── Helpers: switch sidebar avatar between photo and initial ─────────

    function sidebarShowPhoto(url) {
        const el = document.querySelector('.sidebar-user-avatar');
        if (!el) return;
        el.innerHTML                = '';
        el.style.backgroundImage    = `url('${url}')`;
        el.style.backgroundSize     = 'cover';
        el.style.backgroundPosition = 'center';
        el.style.color              = 'transparent';
    }

    function sidebarShowInitial(letter) {
        const el = document.querySelector('.sidebar-user-avatar');
        if (!el) return;
        // Remove ALL inline background overrides so the CSS class gradient
        // (background: linear-gradient(...)) takes over immediately — no refresh needed.
        el.style.removeProperty('background-image');
        el.style.removeProperty('background-size');
        el.style.removeProperty('background-position');
        el.style.removeProperty('color');
        el.textContent = letter;   // restore the initial letter text node
    }

    // Open file picker
    avatarEditBtn?.addEventListener('click', () => photoInput?.click());

    // File selected → validate → open cropper
    photoInput?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Type check
        if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
            showMsg(photoStatus, 'error', 'Unsupported file type. Use JPG, PNG, or WebP.');
            return;
        }
        // 2 MB check (before cropping — raw file)
        if (file.size > 2 * 1024 * 1024) {
            showMsg(photoStatus, 'error', 'Image must not exceed 2 MB.');
            return;
        }

        clearMsg(photoStatus);
        const reader = new FileReader();
        reader.onload = function (e) {
            openCropper(e.target.result);
        };
        reader.readAsDataURL(file);
        // Reset so same file can be selected again
        photoInput.value = '';
    });

    function openCropper(dataUrl) {
        cropperImg.src = dataUrl;
        cropperImg.onload = function () {
            imgNatW = this.naturalWidth;
            imgNatH = this.naturalHeight;
            // Initial zoom: fit the shorter side into the stage
            const stageSize = cropperStage.offsetWidth;
            const fitScale  = stageSize / Math.min(imgNatW, imgNatH);
            scale = fitScale;
            zoomSlider.min   = Math.max(0.1, fitScale * 0.5).toFixed(2);
            zoomSlider.max   = (fitScale * 5).toFixed(2);
            zoomSlider.step  = (fitScale * 0.01).toFixed(4);
            zoomSlider.value = scale;
            // Centre the image
            centerImage(stageSize);
            applyTransform();
        };
        cropperOverlay.classList.add('open');
        clearMsg(cropperMsg);
    }

    function centerImage(stageSize) {
        offsetX = (stageSize - imgNatW * scale) / 2;
        offsetY = (stageSize - imgNatH * scale) / 2;
    }

    function applyTransform() {
        cropperImg.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
        cropperImg.style.width     = imgNatW + 'px';
        cropperImg.style.height    = imgNatH + 'px';
    }

    function clampOffset() {
        const stageSize = cropperStage.offsetWidth;
        const circleR   = stageSize * 0.4;    // 80% of stage = circle diameter
        const circleCX  = stageSize / 2;
        const circleCY  = stageSize / 2;

        // Ensure the circle is always covered by the image
        const imgW = imgNatW * scale;
        const imgH = imgNatH * scale;

        // Image must cover: circle left = circleCX-circleR, right = circleCX+circleR, etc.
        const minX = circleCX + circleR - imgW;   // offsetX >= this
        const maxX = circleCX - circleR;           // offsetX <= this
        const minY = circleCY + circleR - imgH;
        const maxY = circleCY - circleR;

        offsetX = Math.min(maxX, Math.max(minX, offsetX));
        offsetY = Math.min(maxY, Math.max(minY, offsetY));
    }

    // Zoom slider
    zoomSlider?.addEventListener('input', function () {
        const stageSize  = cropperStage.offsetWidth;
        const prevScale  = scale;
        scale = parseFloat(this.value);
        // Keep centre of stage fixed during zoom
        const cx = stageSize / 2;
        const cy = stageSize / 2;
        offsetX = cx - (cx - offsetX) * (scale / prevScale);
        offsetY = cy - (cy - offsetY) * (scale / prevScale);
        clampOffset();
        applyTransform();
    });

    // Drag to reposition
    cropperStage?.addEventListener('pointerdown', function (e) {
        isDragging   = true;
        dragStartX   = e.clientX;
        dragStartY   = e.clientY;
        dragStartOX  = offsetX;
        dragStartOY  = offsetY;
        this.setPointerCapture(e.pointerId);
    });

    cropperStage?.addEventListener('pointermove', function (e) {
        if (!isDragging) return;
        offsetX = dragStartOX + (e.clientX - dragStartX);
        offsetY = dragStartOY + (e.clientY - dragStartY);
        clampOffset();
        applyTransform();
    });

    cropperStage?.addEventListener('pointerup', () => { isDragging = false; });

    // Cancel
    btnCropCancel?.addEventListener('click', () => {
        cropperOverlay.classList.remove('open');
    });

    // Save — draw to canvas, export as WebP, upload
    btnCropSave?.addEventListener('click', async function () {
        const OUTPUT_SIZE = 400;
        const stageSize   = cropperStage.offsetWidth;
        const circleR     = stageSize * 0.4;
        const circleCX    = stageSize / 2;
        const circleCY    = stageSize / 2;

        // Map circle bounds in stage-space to image-space
        const srcX = (circleCX - circleR - offsetX) / scale;
        const srcY = (circleCY - circleR - offsetY) / scale;
        const srcSize = (circleR * 2) / scale;

        // Draw cropped square to off-screen canvas
        const canvas  = document.createElement('canvas');
        canvas.width  = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;
        const ctx     = canvas.getContext('2d');
        ctx.drawImage(cropperImg, srcX, srcY, srcSize, srcSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);

        const dataUri = canvas.toDataURL('image/webp', 0.88);

        // Size check on final image
        const approxBytes = Math.round(dataUri.length * 0.75);
        if (approxBytes > 2 * 1024 * 1024) {
            cropperMsg.textContent = 'Cropped image too large. Try zooming in more.';
            return;
        }

        // Disable button + show spinner
        btnCropSave.disabled = true;
        btnCropSave.innerHTML = '<span class="spinner-sm"></span> Saving…';
        cropperMsg.textContent = '';

        try {
            const { ok, data } = await apiFetch(C.photoUrl, { image: dataUri }, 'POST');
            if (ok && data.success) {
                // ── Update main profile avatar ──────────────────────────
                showPhoto(data.url);

                // ── Update sidebar avatar ───────────────────────────────
                sidebarShowPhoto(data.url);

                cropperOverlay.classList.remove('open');
                showMsg(photoStatus, 'success', 'Profile photo updated successfully.');
            } else {
                cropperMsg.textContent = data.error || 'Upload failed. Please try again.';
            }
        } catch (e) {
            cropperMsg.textContent = 'Network error. Please try again.';
        }

        btnCropSave.disabled = false;
        btnCropSave.innerHTML = '<i class="bi bi-check2 me-1"></i>Save Photo';
    });

    // ── Remove Photo ──────────────────────────────────────────────────────
    removeBtn.addEventListener('click', async function () {
        if (!confirm('Remove your profile photo and revert to the default initial?')) return;

        removeBtn.disabled   = true;
        removeBtn.innerHTML  = '<span class="spinner-sm" style="border-color:rgba(255,255,255,.4);border-top-color:#fff"></span>';

        try {
            const { ok, data } = await apiFetch(DELETE_PHOTO_URL, undefined, 'DELETE');

            if (ok && data.success) {
                const letter = data.initial || (avatarInitial?.textContent?.trim()) || '?';

                // ── Reset main profile avatar to initial letter ──────────
                showInitial(letter);

                // ── Reset sidebar avatar to initial letter ───────────────
                sidebarShowInitial(letter);

                showMsg(photoStatus, 'success', 'Profile photo removed.');
            } else {
                showMsg(photoStatus, 'error', data.error || 'Could not remove photo. Please try again.');
            }
        } catch (e) {
            showMsg(photoStatus, 'error', 'Network error. Please try again.');
        }

        removeBtn.disabled  = false;
        removeBtn.innerHTML = '<i class="bi bi-trash3-fill"></i>';
    });

    /* ════════════════════════════════════════════════════════════════
       Password Strength Meter
    ════════════════════════════════════════════════════════════════ */
    const newPassword     = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const pwStrengthBar   = document.getElementById('pwStrengthBar');
    const pwHint          = document.getElementById('pwHint');

    function measureStrength(pw) {
        let score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return score; // 0-6
    }

    newPassword?.addEventListener('input', function () {
        const pw    = this.value;
        const score = measureStrength(pw);
        const pct   = Math.round((score / 6) * 100);
        const colors = ['#ef4444','#f97316','#eab308','#84cc16','#22c55e','#16a34a'];
        const labels = ['Very weak','Weak','Fair','Good','Strong','Very strong'];
        pwStrengthBar.style.width      = pct + '%';
        pwStrengthBar.style.background = colors[Math.min(score, 5)];
        pwHint.textContent = pw.length > 0 ? labels[Math.min(score, 5)] : '';
    });

    /* ════════════════════════════════════════════════════════════════
       Change Password
    ════════════════════════════════════════════════════════════════ */
    const btnChangePassword = document.getElementById('btnChangePassword');
    const pwStepMsg         = document.getElementById('pwStepMsg');

    btnChangePassword?.addEventListener('click', async function () {
        clearMsg(pwStepMsg);

        const pw  = newPassword?.value     || '';
        const cpw = confirmPassword?.value || '';

        if (pw.length < 8) {
            showMsg(pwStepMsg, 'error', 'Password must be at least 8 characters.');
            return;
        }
        if (!/[A-Z]/.test(pw) || !/[a-z]/.test(pw) || !/[0-9]/.test(pw)) {
            showMsg(pwStepMsg, 'error', 'Password must contain uppercase, lowercase, and a number.');
            return;
        }
        if (pw !== cpw) {
            showMsg(pwStepMsg, 'error', 'Passwords do not match.');
            return;
        }

        btnChangePassword.disabled = true;
        btnChangePassword.innerHTML = '<span class="spinner-sm"></span> Changing…';

        try {
            const { ok, data } = await apiFetch(C.passwordUrl, {
                password:              pw,
                password_confirmation: cpw,
            }, 'POST');

            if (ok && data.success) {
                const passwordSection = document.querySelector('.section-card-body');
                if (passwordSection) {
                    passwordSection.innerHTML = `
                        <div class="inline-alert success" style="justify-content:center;margin-bottom:0.5rem;">
                            <i class="bi bi-check-circle-fill" style="font-size:1.2rem;"></i>
                            <span style="font-size:0.95rem;font-weight:700;">Password changed successfully!</span>
                        </div>
                        <p class="text-center text-muted" style="font-size:0.82rem;margin-top:0.5rem;">
                            A confirmation email has been sent to your registered address.
                        </p>`;
                }
                return;
            }

            const msg = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data.error || data.message || 'Failed to change password. Please try again.');
            showMsg(pwStepMsg, 'error', msg);
        } catch (e) {
            showMsg(pwStepMsg, 'error', 'Network error. Please try again.');
        }

        btnChangePassword.disabled = false;
        btnChangePassword.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Change Password';
    });

})();
