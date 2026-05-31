// CSRF token for non-Inertia fetch requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.csrfToken = token.content;
}
