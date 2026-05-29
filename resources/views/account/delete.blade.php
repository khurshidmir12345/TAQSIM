<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Akkauntni o'chirish — TAQSEEM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0B3C5D;
            --accent: #00A896;
            --danger: #DC2626;
            --bg: #F7F9FB;
            --text: #0F172A;
            --text-mute: #64748B;
            --border: #E2E8F0;
            --card: #FFFFFF;
            --warn-bg: #FEF3C7;
            --warn-text: #92400E;
            --danger-bg: #FEE2E2;
            --danger-text: #991B1B;
            --success-bg: #DCFCE7;
            --success-text: #166534;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .container { width: 100%; max-width: 460px; }
        .card {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(11, 60, 93, 0.06);
            padding: 36px 28px;
            border: 1px solid var(--border);
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 24px;
        }
        .brand-mark {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 16px; letter-spacing: 0.5px;
        }
        .brand-text {
            font-weight: 800; color: var(--primary); letter-spacing: 1px; font-size: 14px;
        }
        h1 {
            font-size: 24px; font-weight: 800; margin: 0 0 8px;
            color: var(--text); line-height: 1.25;
        }
        .lead {
            color: var(--text-mute); font-size: 14px; line-height: 1.55;
            margin: 0 0 24px;
        }
        .warn {
            background: var(--warn-bg); color: var(--warn-text);
            border-radius: 12px; padding: 12px 14px;
            font-size: 13px; line-height: 1.5; margin-bottom: 20px;
            border: 1px solid #FCD34D33;
        }
        .warn ul { margin: 6px 0 0; padding-left: 18px; }
        .warn li { margin: 2px 0; }
        .field { margin-bottom: 14px; }
        .label {
            display: block; font-size: 13px; font-weight: 600;
            color: var(--text); margin-bottom: 6px;
        }
        .input {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: 12px;
            font-size: 15px; font-family: inherit; color: var(--text);
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
        }
        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 168, 150, 0.12);
        }
        .input::placeholder { color: #94A3B8; }
        .hint { font-size: 12px; color: var(--text-mute); margin-top: 6px; }
        .check {
            display: flex; align-items: flex-start; gap: 10px;
            margin: 18px 0 8px; font-size: 13px; color: var(--text);
            cursor: pointer; user-select: none; line-height: 1.5;
        }
        .check input { margin-top: 3px; flex-shrink: 0; accent-color: var(--danger); }
        .btn {
            width: 100%; padding: 14px;
            border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700;
            cursor: pointer; transition: opacity 0.2s, transform 0.05s;
            font-family: inherit;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn:active:not(:disabled) { transform: scale(0.99); }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-ghost {
            background: transparent; color: var(--text-mute);
            font-weight: 500; padding: 10px;
        }
        .btn-ghost:hover { color: var(--primary); }
        .row { display: flex; gap: 10px; margin-top: 14px; }
        .row .btn { flex: 1; }
        .alert {
            border-radius: 10px; padding: 10px 14px;
            font-size: 13px; line-height: 1.45; margin-bottom: 14px;
            display: none;
        }
        .alert.show { display: block; }
        .alert.error { background: var(--danger-bg); color: var(--danger-text); }
        .alert.success { background: var(--success-bg); color: var(--success-text); }
        .step { display: none; }
        .step.active { display: block; }
        .footer {
            text-align: center; margin-top: 20px;
            font-size: 12px; color: var(--text-mute);
        }
        .footer a { color: var(--accent); text-decoration: none; }
        .spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid #ffffff66; border-top-color: #fff;
            border-radius: 50%; animation: spin 0.6s linear infinite;
            vertical-align: middle; margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .countdown { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--accent); }
        .resend { font-size: 13px; text-align: center; margin-top: 10px; color: var(--text-mute); }
        .resend button {
            background: none; border: none; color: var(--accent);
            font-weight: 600; cursor: pointer; padding: 0;
            font-family: inherit; font-size: inherit;
        }
        .resend button:disabled { color: var(--text-mute); cursor: not-allowed; }
        .ok-state {
            text-align: center;
            padding: 12px 0 8px;
        }
        .ok-state .icon {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: var(--success-bg);
            color: var(--success-text);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 30px; margin-bottom: 14px;
        }
        .lang {
            display: flex; gap: 6px; justify-content: flex-end;
            margin-bottom: 12px;
        }
        .lang button {
            background: transparent; border: 1px solid var(--border);
            border-radius: 8px; padding: 4px 10px;
            font-size: 12px; cursor: pointer; color: var(--text-mute);
            font-family: inherit; font-weight: 600;
        }
        .lang button.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        @media (max-width: 420px) {
            .card { padding: 28px 22px; border-radius: 18px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="brand">
            <div class="brand-mark">T</div>
            <div class="brand-text">TAQSEEM</div>
        </div>

        {{-- ─────────── STEP 1: PHONE ─────────── --}}
        <div class="step active" id="step-phone">
            <h1>Akkauntni o'chirish</h1>
            <p class="lead">
                Bu sahifa orqali siz TAQSEEM akkauntingizni va unga bog'liq barcha ma'lumotlarni butunlay o'chirib tashlaysiz.
            </p>

            <div class="warn">
                <strong>Nima o'chiriladi:</strong>
                <ul>
                    <li>Profil ma'lumotlaringiz (ism, telefon, email)</li>
                    <li>Barcha do'konlar va ularning ma'lumotlari</li>
                    <li>Retseptlar, mahsulotlar, xom-ashyolar</li>
                    <li>Ishlab chiqarish, xarajat va hisobotlar</li>
                    <li>Login sessiyalari (barcha qurilmalardan chiqib ketadi)</li>
                </ul>
                <div style="margin-top:8px; font-weight:600;">
                    ⚠️ Bu amal qaytarib bo'lmaydi.
                </div>
            </div>

            <div class="alert error" id="alert-phone"></div>

            <form id="form-phone" autocomplete="off">
                <div class="field">
                    <label class="label" for="phone">Akkauntga bog'langan telefon raqami</label>
                    <input
                        class="input"
                        type="tel"
                        id="phone"
                        name="phone"
                        inputmode="tel"
                        placeholder="+998 90 123 45 67"
                        required
                    >
                    <div class="hint">Telefon raqamingizga 4-xonali tasdiq kodi yuboriladi.</div>
                </div>

                <button class="btn btn-primary" type="submit" id="btn-send">
                    Tasdiq kodini yuborish
                </button>
            </form>
        </div>

        {{-- ─────────── STEP 2: CODE + CONFIRM ─────────── --}}
        <div class="step" id="step-code">
            <h1>Tasdiqlang</h1>
            <p class="lead">
                <strong id="phone-display"></strong> raqamiga yuborilgan 4-xonali kodni kiriting.
            </p>

            <div class="alert error" id="alert-code"></div>

            <form id="form-code" autocomplete="off">
                <input type="hidden" id="phone-hidden" name="phone">

                <div class="field">
                    <label class="label" for="code">Tasdiq kodi</label>
                    <input
                        class="input"
                        type="text"
                        id="code"
                        name="code"
                        inputmode="numeric"
                        maxlength="4"
                        pattern="[0-9]{4}"
                        placeholder="0000"
                        autocomplete="one-time-code"
                        required
                        style="text-align:center; font-size:22px; letter-spacing:8px; font-weight:700;"
                    >
                </div>

                <div class="resend">
                    Kod kelmadi?
                    <span id="resend-wait">Qaytadan yuborish: <span class="countdown" id="countdown">02:00</span></span>
                    <button type="button" id="btn-resend" style="display:none;">Qaytadan yuborish</button>
                </div>

                <label class="check">
                    <input type="checkbox" id="agree" required>
                    <span>
                        Men akkauntim va unga bog'liq <strong>barcha ma'lumotlar butunlay o'chiriladi</strong> hamda bu amalni qaytarib bo'lmasligini tushunaman.
                    </span>
                </label>

                <div class="row">
                    <button class="btn btn-ghost" type="button" id="btn-back">← Ortga</button>
                    <button class="btn btn-danger" type="submit" id="btn-delete" disabled>
                        Akkauntni o'chirish
                    </button>
                </div>
            </form>
        </div>

        {{-- ─────────── STEP 3: SUCCESS ─────────── --}}
        <div class="step" id="step-done">
            <div class="ok-state">
                <div class="icon">✓</div>
                <h1>Akkaunt o'chirildi</h1>
                <p class="lead">
                    Sizning akkauntingiz va barcha ma'lumotlaringiz tizimdan butunlay o'chirildi.
                    Bizdan foydalanganingiz uchun rahmat.
                </p>
            </div>
        </div>

        <div class="footer">
            Yordam kerakmi? <a href="mailto:support@taqseem.uz">support@taqseem.uz</a>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const steps = {
        phone: document.getElementById('step-phone'),
        code:  document.getElementById('step-code'),
        done:  document.getElementById('step-done'),
    };

    function showStep(name) {
        Object.values(steps).forEach(s => s.classList.remove('active'));
        steps[name].classList.add('active');
    }

    function showAlert(id, msg) {
        const el = document.getElementById(id);
        el.textContent = msg;
        el.classList.add('show');
    }
    function hideAlert(id) {
        document.getElementById(id).classList.remove('show');
    }

    function setLoading(btn, loading, originalText) {
        if (loading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span>Yuborilmoqda…';
            btn.disabled = true;
        } else {
            btn.innerHTML = btn.dataset.originalText || originalText;
            btn.disabled = false;
        }
    }

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    }

    // ─── STEP 1: send code ─────────────────────────
    const formPhone = document.getElementById('form-phone');
    const btnSend = document.getElementById('btn-send');
    const inputPhone = document.getElementById('phone');

    formPhone.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert('alert-phone');
        const phone = inputPhone.value.trim();
        if (!phone) return;

        setLoading(btnSend, true);
        const { ok, data } = await post('{{ route("account.delete.sendCode") }}', { phone });
        setLoading(btnSend, false);

        if (!ok || !data.success) {
            showAlert('alert-phone', data.message || 'Xatolik yuz berdi.');
            return;
        }

        document.getElementById('phone-display').textContent = phone;
        document.getElementById('phone-hidden').value = phone;
        showStep('code');
        startCountdown(data.expires_in || 120);
        setTimeout(() => document.getElementById('code').focus(), 100);
    });

    // ─── STEP 2: countdown for resend ──────────────
    let countdownTimer = null;
    function startCountdown(seconds) {
        const cdEl = document.getElementById('countdown');
        const waitEl = document.getElementById('resend-wait');
        const btnResend = document.getElementById('btn-resend');
        btnResend.style.display = 'none';
        waitEl.style.display = '';

        let remaining = seconds;
        function tick() {
            const m = String(Math.floor(remaining / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            cdEl.textContent = `${m}:${s}`;
            if (remaining <= 0) {
                clearInterval(countdownTimer);
                waitEl.style.display = 'none';
                btnResend.style.display = '';
                return;
            }
            remaining--;
        }
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    document.getElementById('btn-resend').addEventListener('click', async () => {
        hideAlert('alert-code');
        const phone = document.getElementById('phone-hidden').value;
        const btn = document.getElementById('btn-resend');
        btn.disabled = true; btn.textContent = 'Yuborilmoqda…';
        const { ok, data } = await post('{{ route("account.delete.sendCode") }}', { phone });
        btn.disabled = false; btn.textContent = 'Qaytadan yuborish';
        if (!ok || !data.success) {
            showAlert('alert-code', data.message || 'Kod yuborib bo\'lmadi.');
            return;
        }
        startCountdown(data.expires_in || 120);
    });

    // ─── STEP 2: enable delete only when checked + 4 digits ───
    const inputCode = document.getElementById('code');
    const inputAgree = document.getElementById('agree');
    const btnDelete = document.getElementById('btn-delete');
    function updateDeleteBtn() {
        btnDelete.disabled = !(inputAgree.checked && inputCode.value.length === 4);
    }
    inputCode.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
        updateDeleteBtn();
    });
    inputAgree.addEventListener('change', updateDeleteBtn);

    // ─── STEP 2: back button ──────────────────────
    document.getElementById('btn-back').addEventListener('click', () => {
        clearInterval(countdownTimer);
        hideAlert('alert-code');
        inputCode.value = '';
        inputAgree.checked = false;
        updateDeleteBtn();
        showStep('phone');
    });

    // ─── STEP 2: confirm delete ───────────────────
    const formCode = document.getElementById('form-code');
    formCode.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert('alert-code');
        if (btnDelete.disabled) return;

        setLoading(btnDelete, true);
        const { ok, data } = await post('{{ route("account.delete.confirm") }}', {
            phone: document.getElementById('phone-hidden').value,
            code:  inputCode.value,
            agree: true,
        });
        setLoading(btnDelete, false);

        if (!ok || !data.success) {
            showAlert('alert-code', data.message || 'Xatolik yuz berdi.');
            return;
        }

        clearInterval(countdownTimer);
        showStep('done');
    });
})();
</script>

</body>
</html>
