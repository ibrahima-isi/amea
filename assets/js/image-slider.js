/**
 * IMAGE SLIDER - Carrousel d'images dynamique
 * Gère automatiquement les différents formats d'images
 */

class ImageSlider {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.imageElement = null;
    this.currentIndex = 0;
    this.isTransitioning = false;

    // Configuration par défaut
    this.config = {
      autoPlay: true,
      interval: 5000,
      showIndicators: true,
      showNavigation: false,
      fade: true,
      adaptToImageRatio: true,
      ...options,
    };

    this.images = [];
    this.alts = [];
    this.timer = null;

    this.init();
  }

  init() {
    if (!this.container) {
      console.error("Container not found");
      return;
    }

    this.imageElement = this.container.querySelector(".slider-image");
    if (!this.imageElement) {
      console.error("Image element not found");
      return;
    }

    // Ajouter les indicateurs si demandé
    if (this.config.showIndicators) {
      this.createIndicators();
    }

    // Ajouter la navigation si demandée
    if (this.config.showNavigation) {
      this.createNavigation();
    }

    // Démarrer le slider automatique
    if (this.config.autoPlay) {
      this.startAutoPlay();
    }

    // Gérer le hover pour pause/resume
    this.setupHoverEvents();

    // Adapter l'image au chargement
    this.imageElement.addEventListener("load", () => {
      this.adaptImageRatio();
    });
  }

  setImages(images, alts = []) {
    this.images = images;
    this.alts =
      alts.length === images.length
        ? alts
        : images.map((_, i) => `Image ${i + 1}`);

    if (this.config.showIndicators) {
      this.updateIndicators();
    }
  }

  adaptImageRatio() {
    if (!this.config.adaptToImageRatio || !this.imageElement.complete) return;

    const img = this.imageElement;
    const aspectRatio = img.naturalWidth / img.naturalHeight;

    // Supprimer les classes précédentes
    img.classList.remove("portrait", "landscape", "square");

    // Ajouter la classe appropriée
    if (aspectRatio > 1.2) {
      img.classList.add("landscape");
    } else if (aspectRatio < 0.8) {
      img.classList.add("portrait");
    } else {
      img.classList.add("square");
    }
  }

  createIndicators() {
    const indicatorsContainer = document.createElement("div");
    indicatorsContainer.className = "slider-indicators";
    this.container.appendChild(indicatorsContainer);
    this.indicatorsContainer = indicatorsContainer;
  }

  updateIndicators() {
    if (!this.indicatorsContainer) return;

    this.indicatorsContainer.innerHTML = "";

    this.images.forEach((_, index) => {
      const indicator = document.createElement("div");
      indicator.className = "slider-indicator";
      if (index === this.currentIndex) {
        indicator.classList.add("active");
      }

      indicator.addEventListener("click", () => {
        this.goToSlide(index);
      });

      this.indicatorsContainer.appendChild(indicator);
    });
  }

  createNavigation() {
    const prevBtn = document.createElement("button");
    prevBtn.className = "slider-nav prev";
    prevBtn.innerHTML = "‹";
    prevBtn.setAttribute("aria-label", "Image précédente");
    prevBtn.addEventListener("click", () => this.previousSlide());

    const nextBtn = document.createElement("button");
    nextBtn.className = "slider-nav next";
    nextBtn.innerHTML = "›";
    nextBtn.setAttribute("aria-label", "Image suivante");
    nextBtn.addEventListener("click", () => this.nextSlide());

    this.container.appendChild(prevBtn);
    this.container.appendChild(nextBtn);
  }

  setupHoverEvents() {
    this.container.addEventListener("mouseenter", () => {
      this.pauseAutoPlay();
    });

    this.container.addEventListener("mouseleave", () => {
      if (this.config.autoPlay) {
        this.startAutoPlay();
      }
    });
  }

  changeImage() {
    if (this.isTransitioning || this.images.length === 0) return;

    this.isTransitioning = true;

    // Effet de transition
    if (this.config.fade) {
      this.imageElement.style.opacity = "0";

      setTimeout(() => {
        this.currentIndex = (this.currentIndex + 1) % this.images.length;
        this.updateImage();

        setTimeout(() => {
          this.imageElement.style.opacity = "1";
          this.isTransitioning = false;
        }, 50);
      }, 500);
    } else {
      this.currentIndex = (this.currentIndex + 1) % this.images.length;
      this.updateImage();
      this.isTransitioning = false;
    }

    this.updateIndicators();
  }

  updateImage() {
    if (this.images.length === 0) return;

    this.imageElement.src = this.images[this.currentIndex];
    this.imageElement.alt = this.alts[this.currentIndex];

    // Gérer les erreurs de chargement
    this.imageElement.onerror = () => {
      console.warn(
        `Erreur de chargement pour l'image: ${this.images[this.currentIndex]}`
      );
      this.imageElement.classList.add("error");
    };

    this.imageElement.onload = () => {
      this.imageElement.classList.remove("error", "loading");
      this.adaptImageRatio();
    };
  }

  goToSlide(index) {
    if (index === this.currentIndex || this.isTransitioning) return;

    this.currentIndex = index;
    this.updateImage();
    this.updateActiveIndicator();
  }

  nextSlide() {
    this.currentIndex = (this.currentIndex + 1) % this.images.length;
    this.updateImage();
    this.updateActiveIndicator();
  }

  previousSlide() {
    this.currentIndex =
      (this.currentIndex - 1 + this.images.length) % this.images.length;
    this.updateImage();
    this.updateActiveIndicator();
  }

  updateActiveIndicator() {
    if (!this.indicatorsContainer) return;

    const indicators =
      this.indicatorsContainer.querySelectorAll(".slider-indicator");
    indicators.forEach((indicator, index) => {
      indicator.classList.toggle("active", index === this.currentIndex);
    });
  }

  startAutoPlay() {
    this.stopAutoPlay();
    if (this.images.length > 1) {
      this.timer = setInterval(() => {
        this.changeImage();
      }, this.config.interval);
    }
  }

  stopAutoPlay() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  pauseAutoPlay() {
    this.stopAutoPlay();
  }

  resumeAutoPlay() {
    if (this.config.autoPlay) {
      this.startAutoPlay();
    }
  }

  destroy() {
    this.stopAutoPlay();

    // Supprimer les événements
    const navButtons = this.container.querySelectorAll(".slider-nav");
    navButtons.forEach((btn) => btn.remove());

    const indicators = this.container.querySelector(".slider-indicators");
    if (indicators) indicators.remove();
  }
}

// Auto-initialisation quand le DOM est prêt
document.addEventListener("DOMContentLoaded", function () {
  // Configuration des images pour AMEA - Commencer par presidents.jpg
  const images = [
    "assets/img/presidents.jpg",
    "assets/img/president-hecm.jpg",
    "assets/img/WhatsApp Image 2025-04-28 at 22.07.41_c84e18a5.jpg",
    "assets/img/WhatsApp Image 2025-05-18 at 10.13.22_67eee142.jpg",
    "assets/img/WhatsApp Image 2025-06-07 at 20.27.41_34080c51.jpg",
    "assets/img/WhatsApp Image 2025-06-15 at 22.51.43_19cf0284.jpg",
    "assets/img/WhatsApp Image 2025-06-15 at 22.51.43_1ae6c235.jpg",
    "assets/img/WhatsApp Image 2025-06-21 at 15.21.01_d7611f3b.jpg",
    "assets/img/WhatsApp Image 2025-06-21 at 15.21.52_431fdb6a.jpg",
    "assets/img/WhatsApp Image 2025-06-21 at 15.21.53_5de32230.jpg",
    "assets/img/WhatsApp Image 2025-06-22 at 10.19.00_36b33f51.jpg",
    "assets/img/WhatsApp Image 2025-07-29 at 17.13.32_545ded6b.jpg",
  ];

  const alts = [
    "Présidents",
    "Président HECM",
    "Événement Amicale - Avril 2025",
    "Activité Amicale - Mai 2025",
    "Réunion Amicale - Juin 2025",
    "Événement Amicale - Mi-juin 2025",
    "Cérémonie Amicale - Mi-juin 2025",
    "Activité communautaire - Juin 2025",
    "Rencontre étudiants - Juin 2025",
    "Événement associatif - Juin 2025",
    "Assemblée Amicale - Juin 2025",
    "Activité récente - Juillet 2025",
  ];

  // Initialiser le slider
  const slider = new ImageSlider("imageSlider", {
    autoPlay: true,
    interval: 2000,
    showIndicators: true,
    showNavigation: false,
    fade: true,
    adaptToImageRatio: true,
  });

  // Charger les images
  slider.setImages(images, alts);

  // Exposer le slider globalement pour le debugging
  window.ameaSlider = slider;
});
