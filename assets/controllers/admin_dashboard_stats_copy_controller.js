import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['text', 'feedback'];

    async copy() {
        if (!this.hasTextTarget) {
            return;
        }

        const text = this.textTarget.value.trim();
        if (text === '') {
            this.showFeedback('Aucune donnée à copier.', true);
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            this.showFeedback('Copié dans le presse-papier.');
        } catch (error) {
            this.textTarget.hidden = false;
            this.textTarget.focus();
            this.textTarget.select();
            this.showFeedback('Copie automatique impossible. Texte sélectionné, fais Ctrl+C.', true);
        }
    }

    showFeedback(message, isError = false) {
        if (!this.hasFeedbackTarget) {
            return;
        }

        this.feedbackTarget.textContent = message;
        this.feedbackTarget.dataset.state = isError ? 'error' : 'ok';
    }
}
