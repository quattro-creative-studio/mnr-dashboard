
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
import 'bootstrap-datepicker'
import 'popper.js'

// TinyMCE is deliberately NOT imported here. It is copied verbatim to
// public/vendor/tinymce and loaded with its own <script> tag by the two admin
// views that need it. Two reasons:
//
//   Licence. Bundling concatenates TinyMCE with this application's own code
//   into a single file, which is what makes the "combined work" argument
//   available to a copyleft licence. Served as a separate, unmodified file it
//   is mere aggregation.
//
//   Weight. app.js is loaded by all four layouts -- every teacher page, every
//   public token page -- while the editor is used on exactly two admin pages.

require('datatables.net');
require('datatables.net-bs4');

// French defaults for every DataTable in the admin.
//
// This exists because the views used to render their own "no rows" line as a
// single <td colspan="N">. DataTables cannot map a spanning cell onto its
// columns and aborts with "Incorrect column count" -- an alert() box, on the
// first page an administrator sees, whenever a table happens to be empty.
// Letting DataTables render its own empty state removes the whole class of
// failure; these strings keep it in French.
$.extend(true, $.fn.dataTable.defaults, {
    language: {
        emptyTable: 'Aucune donnée disponible',
        zeroRecords: 'Aucun résultat',
        info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
        infoEmpty: 'Aucune entrée',
        infoFiltered: '(filtré sur _MAX_ entrées au total)',
        lengthMenu: 'Afficher _MENU_ entrées',
        search: 'Rechercher :',
        paginate: {
            first: 'Premier',
            last: 'Dernier',
            next: 'Suivant',
            previous: 'Précédent',
        },
    },
});

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
