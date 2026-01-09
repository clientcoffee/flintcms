<?php
$title = 'Admin Login';
$bodyClass = 'bg-[#FAFAFA] text-gray-800 antialiased';

ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-6">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-900 mb-2">Admin Login</h1>
        <p class="text-sm text-gray-500 mb-6">Sign in to manage your Flint content.</p>
        <div id="login-message" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm"></div>
        <form id="login-form" class="space-y-4">
            <div>
                <label for="admin-password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" id="admin-password" name="password" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </div>
            <div class="text-xs text-gray-500">
                <button type="button" id="login-email-link" class="text-indigo-600 hover:text-indigo-700 font-medium">Log in via Email</button>
                <span class="mx-2 text-gray-300">|</span>
                <button type="button" id="forgot-password-link" class="text-indigo-600 hover:text-indigo-700 font-medium">Forgot Password</button>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Sign In</button>
        </form>
    </div>
</div>
<script>
    const loginForm = document.getElementById("login-form");
    const loginMessage = document.getElementById("login-message");
    const passwordInput = document.getElementById("admin-password");
    const loginEmailLink = document.getElementById("login-email-link");
    const forgotPasswordLink = document.getElementById("forgot-password-link");

    const setLoginMessage = (message, tone = "info", link = null) => {
        loginMessage.classList.add("hidden");
        loginMessage.classList.remove(
            "border-emerald-200",
            "bg-emerald-50",
            "text-emerald-700",
            "border-red-200",
            "bg-red-50",
            "text-red-700",
            "border-indigo-200",
            "bg-indigo-50",
            "text-indigo-700"
        );

        loginMessage.textContent = message;
        if (tone === "success") {
            loginMessage.classList.add("border-emerald-200", "bg-emerald-50", "text-emerald-700");
        } else if (tone === "error") {
            loginMessage.classList.add("border-red-200", "bg-red-50", "text-red-700");
        } else {
            loginMessage.classList.add("border-indigo-200", "bg-indigo-50", "text-indigo-700");
        }

        if (link) {
            const linkWrapper = document.createElement("div");
            linkWrapper.className = "mt-2 text-xs";
            const linkEl = document.createElement("a");
            linkEl.href = link;
            linkEl.textContent = "Open magic link";
            linkEl.className = "text-indigo-600 hover:text-indigo-700 underline break-all";
            linkWrapper.appendChild(linkEl);
            loginMessage.appendChild(linkWrapper);
        }

        loginMessage.classList.remove("hidden");
    };

    loginForm?.addEventListener("submit", async (event) => {
        event.preventDefault();

        const passwordValue = passwordInput?.value || "";
        if (!passwordValue) {
            setLoginMessage("Password is required.", "error");
            return;
        }

        try {
            const loginResponse = await fetch("/api/login", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ password: passwordValue })
            });
            const responseData = await loginResponse.json();

            if (!responseData.success) {
                setLoginMessage(responseData.error || "Login failed.", "error");
                return;
            }

            setLoginMessage("Welcome back! Redirecting...", "success");
            window.location.href = "/";
        } catch (error) {
            console.error("Login error:", error);
            setLoginMessage("Login failed. Please try again.", "error");
        }
    });

    const requestMagicLink = async (mode) => {
        try {
            setLoginMessage("Sending a magic link...", "info");
            const response = await fetch("/api/login/magic", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ mode })
            });
            const data = await response.json();

            if (!data.success) {
                setLoginMessage(data.error || "Failed to send magic link.", "error");
                return;
            }

            setLoginMessage(
                data.message || "Check your email for the magic link.",
                "success",
                data.magic_link || null
            );
        } catch (error) {
            console.error("Magic link error:", error);
            setLoginMessage("Failed to send magic link. Please try again.", "error");
        }
    };

    loginEmailLink?.addEventListener("click", () => {
        requestMagicLink("login");
    });

    forgotPasswordLink?.addEventListener("click", () => {
        requestMagicLink("reset");
    });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/admin.php';
?>
