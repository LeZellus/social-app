// assets/controllers/sidebar_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["sidebar", "overlay"]
    
    connect() {
        console.log("Sidebar controller connecté") // Pour debug
        this.createOverlay()
    }
    
    // Méthode pour basculer l'état de la sidebar
    toggle() {
        console.log("Toggle sidebar") // Pour debug
        
        const sidebar = this.sidebarTarget
        const isOpen = !sidebar.classList.contains('-translate-x-full')
        
        if (isOpen) {
            this.close()
        } else {
            this.open()
        }
    }
    
    // Ouvrir la sidebar
    open() {
        console.log("Opening sidebar") // Pour debug
        
        this.sidebarTarget.classList.remove('-translate-x-full')
        this.sidebarTarget.classList.add('translate-x-0')
        
        // Afficher l'overlay
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.remove('hidden')
        }
        
        // Empêcher le scroll du body
        document.body.style.overflow = 'hidden'
    }
    
    // Fermer la sidebar
    close() {
        console.log("Closing sidebar") // Pour debug
        
        this.sidebarTarget.classList.add('-translate-x-full')
        this.sidebarTarget.classList.remove('translate-x-0')
        
        // Masquer l'overlay
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.add('hidden')
        }
        
        // Permettre le scroll du body
        document.body.style.overflow = ''
    }
    
    // Fermer quand on clique sur l'overlay
    closeOnOverlay(event) {
        if (event.target === this.overlayTarget) {
            this.close()
        }
    }
    
    // Créer l'overlay dynamiquement
    createOverlay() {
        // Vérifier si l'overlay existe déjà
        if (document.getElementById('sidebar-overlay')) {
            return
        }
        
        const overlay = document.createElement('div')
        overlay.id = 'sidebar-overlay'
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-30 hidden sm:hidden'
        overlay.setAttribute('data-sidebar-target', 'overlay')
        overlay.setAttribute('data-action', 'click->sidebar#closeOnOverlay')
        
        document.body.appendChild(overlay)
    }
    
    // Gérer les touches clavier (Échap pour fermer)
    handleKeydown(event) {
        if (event.key === 'Escape') {
            this.close()
        }
    }
}