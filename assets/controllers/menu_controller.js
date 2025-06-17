// assets/controllers/menu_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["menu"]
    
    connect() {
        console.log("Menu controller connecté") // Pour debug
    }
    
    toggle(event) {
        event.stopPropagation()
        event.preventDefault()
        
        console.log("Toggle menu") // Pour debug
        
        // Fermer tous les autres menus
        document.querySelectorAll('.post-menu').forEach(menu => {
            if (menu !== this.menuTarget) {
                menu.classList.add('hidden')
            }
        })
        
        // Toggle le menu actuel
        this.menuTarget.classList.toggle('hidden')
    }
    
    // Méthode appelée quand on clique en dehors
    closeOnClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden')
        }
    }
}