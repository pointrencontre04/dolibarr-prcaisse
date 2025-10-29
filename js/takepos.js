jQuery(document).ready(function() {
    if (jQuery('body.bodytakepos').length > 0) {
        var currentAction = '';
        jQuery(document).on('keypress', function (e) {
            console.log(e.originalEvent.charCode);
            switch (e.originalEvent.charCode) {
                case 42: // '*'
                Edit('qty');
                currentAction = 'qty';
                e.preventDefault();
                break;

                case 43: // '+'
                Edit('p');
                currentAction = 'p';
                e.preventDefault();
                break;

                case 46: // '.'
                    Edit('.');
                    e.preventDefault();
                break;

                case 13: // 'enter'
                    if (currentAction == 'qty') {
                        Edit('qty');
                        currentAction = '';
                        e.preventDefault();
                    }
                    if (currentAction == 'p') {
                        Edit('p');
                        currentAction = '';
                        e.preventDefault();
                    }
                break;
            }
            if (e.originalEvent.charCode >= 48 && e.originalEvent.charCode <= 57) { // 0-9
                Edit(e.originalEvent.key);
                e.preventDefault();
            }
        });
    }
});