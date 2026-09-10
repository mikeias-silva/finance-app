<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Finance App</title>

    {{-- Aplica o tema antes de renderizar, senão a página pisca clara ao carregar. --}}
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', saved || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* Subconjunto das variáveis de layouts/app.blade.php — a tela de login não
           estende aquele layout (não tem sidebar nem topbar), mas precisa ser
           reconhecivelmente o mesmo produto. */
        :root, [data-bs-theme="light"] {
            --body-bg: #f8fafc;
            --surface: #ffffff;
            --card-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --input-border: #e2e8f0;
            --input-focus: #94a3b8;
            --btn-primary-bg: #0f172a;
            --btn-primary-hover: #1e293b;
            --brand-bg: #0f172a;
            --brand-text: #f8fafc;
            --brand-muted: #94a3b8;
            --red-text: #dc2626;
            --red-soft: #fee2e2;
            --shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        [data-bs-theme="dark"] {
            --body-bg: #0b1220;
            --surface: #121b2e;
            --card-border: #263353;
            --text-primary: #e8eef9;
            --text-muted: #a3b1c6;
            --input-border: #263353;
            --input-focus: #64748b;
            --btn-primary-bg: #2563eb;
            --btn-primary-hover: #1d4ed8;
            --brand-bg: #080d17;
            --brand-text: #f1f5f9;
            --brand-muted: #8b9ab1;
            --red-text: #f87171;
            --red-soft: rgba(248,113,113,.16);
            --shadow: 0 1px 3px rgba(0,0,0,.4);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: .75rem;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* Mesmo tom da sidebar do app: é o que identifica o produto à primeira vista. */
        .login-brand {
            background: var(--brand-bg);
            border-bottom: 1px solid var(--card-border);
            padding: 1.5rem;
        }

        .login-brand h1 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--brand-text);
            margin: 0;
            letter-spacing: -.01em;
        }

        .login-brand p {
            font-family: 'DM Mono', monospace;
            font-size: .75rem;
            color: var(--brand-muted);
            margin: .25rem 0 0;
        }

        .login-body { padding: 1.5rem; }

        .form-label {
            font-size: .8125rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: .375rem;
        }

        .form-control {
            background: var(--surface);
            border-color: var(--input-border);
            color: var(--text-primary);
            padding: .625rem .75rem;
        }

        .form-control:focus {
            background: var(--surface);
            border-color: var(--input-focus);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(148,163,184,.25);
        }

        .btn-enter {
            width: 100%;
            background: var(--btn-primary-bg);
            border: 1px solid var(--btn-primary-bg);
            color: #f8fafc;
            font-weight: 500;
            padding: .625rem;
            transition: background-color .15s ease;
        }

        .btn-enter:hover, .btn-enter:focus {
            background: var(--btn-primary-hover);
            border-color: var(--btn-primary-hover);
            color: #f8fafc;
        }

        .btn-reveal {
            border: 1px solid var(--input-border);
            border-left: 0;
            color: var(--text-muted);
            background: var(--surface);
        }

        .btn-reveal:hover { color: var(--text-primary); }

        .login-error {
            background: var(--red-soft);
            color: var(--red-text);
            font-size: .8125rem;
            border-radius: .5rem;
            padding: .625rem .75rem;
            margin-bottom: 1rem;
        }

        :focus-visible { outline: 2px solid var(--input-focus); outline-offset: 2px; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="login-brand">
            <h1>Finance App</h1>
            <p>Acesso restrito</p>
        </div>

        <div class="login-body">
            @error('password')
                <div class="login-error" role="alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <label for="password" class="form-label">Senha</label>

                <div class="input-group mb-3">
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           autocomplete="current-password"
                           autofocus
                           required>
                    <button class="btn btn-reveal" type="button" id="reveal" aria-label="Mostrar senha">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-enter">Entrar</button>
            </form>
        </div>
    </main>

    <script>
        const field = document.getElementById('password');
        const reveal = document.getElementById('reveal');

        reveal.addEventListener('click', () => {
            const hidden = field.type === 'password';
            field.type = hidden ? 'text' : 'password';
            reveal.setAttribute('aria-label', hidden ? 'Ocultar senha' : 'Mostrar senha');
            reveal.querySelector('i').className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
            field.focus();
        });
    </script>
</body>
</html>
