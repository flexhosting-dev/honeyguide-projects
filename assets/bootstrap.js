import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Disable Stimulus debug mode
app.debug = false;

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
