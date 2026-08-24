
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
import 'bootstrap-datepicker'
import 'popper.js'

import tinymce from 'tinymce/tinymce';
import 'tinymce/icons/default';
import 'tinymce/themes/silver';
// New in TinyMCE 6: the DOM model is a separate entry point, and a
// bundled build that omits it silently never initialises the editor.
import 'tinymce/models/dom';
import 'tinymce/plugins/link';

require('datatables.net');
require('datatables.net-bs4');

$('[data-toggle="tooltip"]').tooltip();

let oldInputLabel = [];
// show selected file in file chooser
$('input[type=file]').change(function () {
    let label = $(this).parent().find('label');
    if (typeof oldInputLabel[label] === "undefined") {
        // save initial label text for later use
        oldInputLabel[label] = label.text();
    }
    if (typeof $(this)[0].files[0] === "undefined") {
        // use initial text if no file is selected
        label.text(oldInputLabel[label]);
    } else {
        let file = $(this)[0].files[0].name;
        label.text(file);
    }
});
