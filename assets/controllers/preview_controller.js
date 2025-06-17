// assets/controllers/preview_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["title", "content", "preview"]
    
    connect() {
        console.log("Preview controller connecté") // Pour debug
        this.updatePreview()
    }
    
    // Méthode appelée quand le titre change
    titleChanged() {
        console.log("Title changed") // Pour debug
        this.updatePreview()
    }
    
    // Méthode appelée quand le contenu change
    contentChanged() {
        console.log("Content changed") // Pour debug
        this.updatePreview()
    }
    
    updatePreview() {
        const title = this.titleTarget.value.trim()
        const content = this.contentTarget.value.trim()
        
        console.log("Updating preview - Title:", title.length, "Content:", content.length) // Pour debug
        
        if (title || content) {
            let preview = ''
            
            if (title) {
                preview += `<strong>${this.escapeHtml(title)}</strong>`
            }
            
            if (title && content) {
                preview += '<br><br>'
            }
            
            if (content) {
                const truncatedContent = content.length > 200 
                    ? content.substring(0, 200) + '...' 
                    : content
                preview += this.escapeHtml(truncatedContent)
            }
            
            this.previewTarget.innerHTML = preview
        } else {
            this.previewTarget.innerHTML = '<em class="text-gray-500">Saisissez votre contenu pour voir l\'aperçu...</em>'
        }
    }
    
    // Fonction utilitaire pour échapper le HTML
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }
}