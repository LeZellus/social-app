// assets/controllers/publish_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["publishOption", "scheduledContainer", "submitText"]
    
    connect() {
        console.log("Publish controller connecté") // Pour debug
        this.updateInterface()
    }
    
    // Méthode appelée quand l'option de publication change
    optionChanged() {
        console.log("Option changed:", this.publishOptionTarget.value) // Pour debug
        this.updateInterface()
    }
    
    updateInterface() {
        const selectedValue = this.publishOptionTarget.value
        
        console.log("Updating interface for:", selectedValue) // Pour debug
        console.log("Has scheduledContainer target:", this.hasScheduledContainerTarget) // Debug
        
        // Gérer l'affichage du conteneur de date programmée
        if (this.hasScheduledContainerTarget) {
            console.log("Container found, changing display") // Debug
            if (selectedValue === 'schedule') {
                this.scheduledContainerTarget.style.display = 'block'
                console.log("Container should be visible") // Debug
            } else {
                this.scheduledContainerTarget.style.display = 'none'
                console.log("Container should be hidden") // Debug
            }
        } else {
            console.log("ERROR: scheduledContainer target not found!") // Debug
        }
        
        // Mettre à jour le texte du bouton
        if (this.hasSubmitTextTarget) {
            switch (selectedValue) {
                case 'schedule':
                    this.submitTextTarget.textContent = 'Programmer le post'
                    break
                case 'now':
                    this.submitTextTarget.textContent = 'Publier maintenant'
                    break
                case 'draft':
                default:
                    this.submitTextTarget.textContent = 'Sauvegarder en brouillon'
                    break
            }
        } else {
            console.log("ERROR: submitText target not found!") // Debug
        }
    }
}