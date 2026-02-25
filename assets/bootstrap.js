import { startStimulusApp } from '@symfony/stimulus-bundle';
import JourLivraisonFormController from './controllers/jour_livraison_form_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('jour-livraison-form', JourLivraisonFormController);
