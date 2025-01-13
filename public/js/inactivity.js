document.addEventListener('DOMContentLoaded', function () {
    const updateLastActivityCookie = () => {
        const expiryDate = new Date();
        expiryDate.setTime(expiryDate.getTime() + 6000000); // 20 minutes
        document.cookie = `last_activity=${Date.now()}; path=/; expires=${expiryDate.toUTCString()}`;
    };

    // Mettez à jour le cookie à chaque interaction utilisateur
    document.addEventListener('mousemove', updateLastActivityCookie);
    document.addEventListener('keypress', updateLastActivityCookie);
    document.addEventListener('click', updateLastActivityCookie);
    document.addEventListener('scroll', updateLastActivityCookie);
    document.addEventListener('input', updateLastActivityCookie);
    document.addEventListener('change', updateLastActivityCookie);
    document.addEventListener('touchstart', updateLastActivityCookie);
    document.addEventListener('touchend', updateLastActivityCookie);
    window.addEventListener('focus', updateLastActivityCookie);


    // Initialisez le cookie à chaque chargement de page
    updateLastActivityCookie();
});
