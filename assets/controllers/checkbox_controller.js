// assets/controllers/checkbox_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["checkbox", "indicator", "checkmark"]
    
    connect() {
        console.log("Checkbox controller connecté") // Pour debug
        this.updateVisualState()
    }
    
    // Méthode appelée quand on clique sur le label
    toggle(event) {
        // Si on clique directement sur la checkbox, laisser le comportement normal
        if (event.target === this.checkboxTarget) {
            return
        }
        
        console.log("Toggle checkbox via label") // Pour debug
        
        event.preventDefault()
        this.checkboxTarget.checked = !this.checkboxTarget.checked
        
        // Déclencher l'événement change pour la cohérence
        this.checkboxTarget.dispatchEvent(new Event('change', { bubbles: true }))
        
        this.updateVisualState()
    }
    
    // Méthode appelée quand la checkbox change (via clic direct ou programmation)
    checkboxChanged() {
        console.log("Checkbox changed") // Pour debug
        this.updateVisualState()
    }
    
    updateVisualState() {
        const isChecked = this.checkboxTarget.checked
        
        console.log("Updating visual state:", isChecked) // Pour debug
        
        // Mettre à jour l'indicateur
        if (this.hasIndicatorTarget) {
            if (isChecked) {
                this.indicatorTarget.classList.add('bg-blue-600', 'border-blue-600')
                this.indicatorTarget.classList.remove('bg-white', 'dark:bg-gray-700', 'border-gray-300', 'dark:border-gray-600')
            } else {
                this.indicatorTarget.classList.remove('bg-blue-600', 'border-blue-600')
                this.indicatorTarget.classList.add('bg-white', 'dark:bg-gray-700', 'border-gray-300', 'dark:border-gray-600')
            }
        }
        
        // Mettre à jour la coche
        if (this.hasCheckmarkTarget) {
            this.checkmarkTarget.classList.toggle('hidden', !isChecked)
        }
        
        // Mettre à jour le style du label parent
        this.element.classList.toggle('ring-2', isChecked)
        this.element.classList.toggle('ring-blue-500', isChecked)
        this.element.classList.toggle('border-blue-300', isChecked)
    }
}