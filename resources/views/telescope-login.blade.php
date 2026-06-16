<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telescope Monitoring Login | Student Star System</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #070b15;
        }
    </style>
</head>
<body class="min-h-screen text-white flex flex-col justify-center items-center relative overflow-hidden px-4">
    <!-- Pulsing ambient backdrop glows -->
    <div class="absolute top-[-20%] left-[-10%] w-[60vw] h-[60vw] bg-emerald-500/10 rounded-full blur-[150px] pointer-events-none animate-pulse"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[60vw] h-[60vw] bg-blue-500/10 rounded-full blur-[150px] pointer-events-none animate-pulse"></div>

    <div class="w-full max-w-md z-10 space-y-8">
        <!-- Logo Header -->
        <div class="text-center space-y-4">
            <div class="inline-flex p-4 bg-gradient-to-tr from-[#00a67e] to-[#00f2ad] rounded-2xl shadow-[0_0_30px_rgba(0,166,126,0.3)]">
                <!-- SVG Telescope Icon -->
                <svg class="w-12 h-12 text-slate-950" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </div>
            <div>
                <h4 class="text-[#00f2ad] text-xs font-black tracking-[0.3em] uppercase">SYSTEM MONITORING</h4>
                <h1 class="text-3xl font-extrabold text-slate-100 tracking-tight mt-1">Laravel Telescope</h1>
            </div>
        </div>

        <!-- Login Card -->
        <div class="bg-[#0a0f1d]/80 backdrop-blur-md rounded-3xl p-8 border border-white/10 shadow-2xl space-y-6">
            <div class="space-y-2 text-center">
                <p class="text-sm text-slate-400">Please enter the system monitor password to authenticate your session.</p>
            </div>

            <!-- Form -->
            <form action="{{ route('telescope.login') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Password Input -->
                <div class="space-y-2">
                    <label for="password" class="text-xs font-black uppercase tracking-wider text-slate-400">Monitor Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        placeholder="••••••••••••" 
                        required
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3.5 text-white placeholder-slate-600 focus:outline-none focus:border-[#00f2ad] focus:ring-1 focus:ring-[#00f2ad] transition-all"
                    >
                </div>

                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl p-4 text-sm font-semibold space-y-1">
                        @foreach ($errors->all() as $error)
                            <p>⚠️ {{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-[#00a67e] to-[#00f2ad] text-slate-950 font-extrabold py-4 px-4 rounded-xl shadow-[0_0_20px_rgba(0,166,126,0.2)] hover:shadow-[0_0_30px_rgba(0,166,126,0.3)] transform hover:scale-[1.01] active:scale-[0.99] transition-all"
                >
                    Authenticate Session
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-slate-500">
            <p>Protected resource. Back to <a href="/" class="text-[#00f2ad] hover:underline">Leaderboard</a></p>
        </div>
    </div>
</body>
</html>
