<style>
    /* Navbar Styles */
    .navbar {
        position: fixed;
        top: 0;
        width: 100%;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-border);
        z-index: 1000;
        transition: all 0.3s ease;
        padding: 0;
        box-shadow: 0 2px 20px rgba(95, 52, 217, 0.08);
    }

    .navbar.scrolled {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(25px);
        box-shadow: 0 4px 30px rgba(95, 52, 217, 0.12);
    }

    .nav-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.2rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logoIndex {
        font-size: 1.8rem;
        font-weight: 900;
        color: white;
        text-decoration: none;
        background: linear-gradient(135deg, #5f34d9, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        width: 180px;
        transition: transform 0.3s ease;
    }

    .logoIndex:hover {
        transform: scale(1.05);
    }

    .logoIndex img {
        width: 100%;
        height: auto;
        display: block;
    }

    .nav-menu {
        display: flex;
        list-style: none;
        gap: 2.5rem;
        align-items: center;
        margin: 0;
        padding: 0;
    }

    .nav-menu li {
        margin: 0;
    }

    .nav-link {
        color: var(--text-primary);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        position: relative;
        padding: 0.5rem 0;
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        transition: width 0.3s ease;
    }

    .nav-link:hover {
        color: var(--primary);
    }

    .nav-link:hover::after {
        width: 100%;
    }

    .login-btn {
        color: var(--primary) !important;
        font-weight: 600;
    }

    .nav-cta {
        background: linear-gradient(135deg, #5f34d9, #8b5cf6);
        color: white !important;
        padding: 0.75rem 1.8rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(95, 52, 217, 0.3);
        display: inline-block;
    }

    .nav-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(95, 52, 217, 0.4);
    }

    .mobile-toggle {
        display: none;
        color: #2f2264;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
        transition: all 0.3s ease;
    }

    .mobile-toggle:hover {
        color: var(--primary);
    }

    .mobile-toggle i {
        transition: transform 0.3s ease;
    }


    /* Mobile Styles */
    @media screen and (max-width: 968px) {
        .nav-container {
            padding: 1rem 1.5rem;
        }

        .logoIndex {
            width: 140px;
        }

        .mobile-toggle {
            display: block;
        }

        .nav-menu {
            position: fixed;
            left: -100%;
            top: 73px;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            width: 100%;
            height: calc(100vh - 73px);
            text-align: center;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 40px rgba(95, 52, 217, 0.15);
            padding: 3rem 0;
            gap: 0;
            overflow-y: auto;
            justify-content: flex-start;
        }

        .nav-menu.active {
            left: 0;
        }

        .nav-menu li {
            width: 100%;
            padding: 0;
            margin: 0;
            opacity: 0;
            transform: translateX(-20px);
            animation: slideIn 0.4s ease forwards;
        }

        .nav-menu.active li:nth-child(1) { animation-delay: 0.1s; }
        .nav-menu.active li:nth-child(2) { animation-delay: 0.15s; }
        .nav-menu.active li:nth-child(3) { animation-delay: 0.2s; }
        .nav-menu.active li:nth-child(4) { animation-delay: 0.25s; }
        .nav-menu.active li:nth-child(5) { animation-delay: 0.3s; }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-link {
            display: block;
            width: 100%;
            padding: 1.2rem 2rem;
            font-size: 1.1rem;
            border-bottom: 1px solid rgba(95, 52, 217, 0.08);
        }

        .nav-link::after {
            display: none;
        }

        .nav-menu li:last-child .nav-link {
            border-bottom: none;
        }

        .nav-cta {
            margin-top: 1rem;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
        }

        .mobile-toggle i.fa-times {
            transform: rotate(90deg);
        }
    }

    @media screen and (max-width: 480px) {
        .nav-container {
            padding: 1rem;
        }

        .logoIndex {
            width: 120px;
        }

        .nav-menu {
            top: 65px;
            height: calc(100vh - 65px);
        }

        .nav-link {
            padding: 1rem 1.5rem;
            font-size: 1rem;
        }

        .nav-cta {
            padding: 0.9rem 2rem;
            font-size: 1rem;
        }

        h2 {
            font-size: 2rem;
        }

        section {
            padding: 2rem 1.5rem;
        }
    }
</style>

<nav class="navbar" id="navbar">
    <div class="nav-container">
        <a href="./home" class="logoIndex">
            <img src="./assets/img/dark_logo.png" alt="Logo" />
        </a>
        <ul class="nav-menu" id="nav-menu">
            <li><a href="./home" class="nav-link">Home</a></li>
            <li><a href="./home#pricing" class="nav-link">Pricing</a></li>
            <li><a href="./contact" class="nav-link">Support</a></li>
            <li><a href="./lg" class="nav-link login-btn">Login</a></li>
            <li><a href="./lg?plan=starter" class="nav-cta">Start Free Trial</a></li>
        </ul>
        <div class="mobile-toggle" id="mobile-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<script>
    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobile-toggle');
    const navMenu = document.getElementById('nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    const body = document.body;

    // Toggle menu when hamburger is clicked
    mobileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        navMenu.classList.toggle('active');
        
        // Change icon between bars and times
        const icon = mobileToggle.querySelector('i');
        if (navMenu.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
            body.style.overflow = 'hidden';
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
            body.style.overflow = '';
        }
    });

    // Close menu when a link is clicked
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            const icon = mobileToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
            body.style.overflow = '';
        });
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !mobileToggle.contains(e.target)) {
            if (navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                const icon = mobileToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                body.style.overflow = '';
            }
        }
    });

    // Prevent menu from closing when clicking inside it
    navMenu.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>
