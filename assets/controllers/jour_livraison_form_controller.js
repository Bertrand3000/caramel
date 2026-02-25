import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { initialChecked: Boolean };
    static targets = ['toggle', 'fields'];

    connect() {
        if (this.hasToggleTarget) {
            this.toggleTarget.checked = this.initialCheckedValue;
        }
        this.toggle();
    }

    toggle() {
        const show = this.hasToggleTarget && this.toggleTarget.checked;
        this.fieldsTargets.forEach((element) => {
            element.hidden = !show;
        });
    }
}
