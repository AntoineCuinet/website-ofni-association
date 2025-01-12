"use strict";

document.addEventListener("DOMContentLoaded", function() {

    /* Navbar (animation) */
    const header = document.querySelector('#navbar');
    window.addEventListener("scroll", () => {
        header.classList.toggle("sticky", window.scrollY > 0);

        // if(document.documentElement.scrollTop > window.innerHeight * 0.7)
        //     toTopBtn.classList.add("active");
        // else 
        //     toTopBtn.classList.remove("active");
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
});
