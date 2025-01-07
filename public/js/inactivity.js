document.addEventListener('DOMContentLoaded', function () {
    const updateLastActivityCookie = () => {
        const expiryDate = new Date();
        expiryDate.setTime(expiryDate.getTime() + 60000); // 20 minutes
        document.cookie = `last_activity=${Date.now()}; path=/; expires=${expiryDate.toUTCString()}`;
    };

    // Mettez à jour le cookie à chaque interaction utilisateur
    document.addEventListener('mousemove', updateLastActivityCookie);
    document.addEventListener('keypress', updateLastActivityCookie);

    // Initialisez le cookie à chaque chargement de page
    updateLastActivityCookie();
});
