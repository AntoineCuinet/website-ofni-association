"use strict";

document.addEventListener("DOMContentLoaded", function() {

    /* Navbar + to-top-btn (animation) */
    const header = document.querySelector('#navbar'); 
    const toTopBtn = document.querySelector("#to-top-btn");
    window.addEventListener("scroll", () => {
        header.classList.toggle("sticky", window.scrollY > 0);

        if(document.documentElement.scrollTop > window.innerHeight)
            toTopBtn.classList.add("active");
        else 
            toTopBtn.classList.remove("active");
    });
    toTopBtn.addEventListener("click", () => {
        if (toTopBtn.classList.contains("active")) {
            window.scrollTo({
                top: 0
            });
        }
    });

    /* Navbar (hamburger) */
    const hamburgerToggler = document.querySelector(".hamburger");
    const navLinksContainer = document.querySelector(".navlinks-container");
    const toggleNav = () => {
        hamburgerToggler.classList.toggle("open");

        const ariaToggle = hamburgerToggler.getAttribute("aria-expanded") === "true" ? "false" : "true";
        hamburgerToggler.setAttribute("aria-expanded", ariaToggle);

        navLinksContainer.classList.toggle("open");
    }
    hamburgerToggler.addEventListener("click", toggleNav);


    /* Navbar (fermer le menu si clic n'importe où sur le document) */
    document.addEventListener("click", function (event) {
        // Vérifiez si le clic a eu lieu à l'extérieur du menu
        if (!navLinksContainer.contains(event.target) && !hamburgerToggler.contains(event.target)) {
            // Si c'est le cas, fermez le menu en retirant la classe "open"
            navLinksContainer.classList.remove("open");
            hamburgerToggler.classList.remove("open");
        }
    });

    /* Navbar (Supprimer la class boutton lors du redimensionnement de la fenêtre) */
    const lastLink = document.querySelector(".navlinks-container a:last-child");

    function updateLastLink() {
        if (window.innerWidth < 990) {
            lastLink.classList.remove("btn"); // Supprimer la classe 'btn'
        } else {
            lastLink.classList.add("btn"); // Réajouter la classe 'btn' si besoin
        }
    }
    
    // Appliquer la logique au chargement de la page
    updateLastLink();
    
    // Appliquer la logique au redimensionnement de la fenêtre
    window.addEventListener("resize", updateLastLink);



    /* Resize for textarea */
    document.querySelectorAll('textarea').forEach(el => {
        el.style.height = el.scrollHeight + 'px';
        el.classList.add('auto');
        el.addEventListener('input', e => {
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight) + 'px';
        });
    });
});
