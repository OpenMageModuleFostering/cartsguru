(function(document) {
    document.addEventListener('DOMContentLoaded', setupTracking);

    var fields = {
            firstname: 'billing:firstname',
            lastname: 'billing:lastname',
            telephone: 'billing:telephone',
            email: 'billing:email',
            country: 'billing:country_id'
        };

    function setupTracking () {
        for (var item in fields) {
            if (fields.hasOwnProperty(item)) {
                if (Array.isArray(fields[item])) {
                    for (var i = 0; i < fields[item].length; i++) {
                        var el = document.getElementById(fields[item][i]);
                        if (el) {
                            fields[item] = el;
                            break;
                        }
                    }
                } else {
                    fields[item] = document.getElementById(fields[item]);
                }
            }
        }
        if (fields.email && fields.firstname) {
            for (item in fields) {
                if (fields.hasOwnProperty(item)) {
                    fields[item].addEventListener('blur', trackData);
                }
            }
        }
    }

    function collectData () {
        var data = [];
        for (var item in fields) {
            if (fields.hasOwnProperty(item)) {
                // Only if email is set
                if (item === 'email' && fields[item].value === '') {
                    return false;
                }
                if (item === 'country') {
                    data.push((encodeURIComponent(item) + "=" + encodeURIComponent(fields[item].options[fields[item].selectedIndex].value)));
                } else {
                    data.push((encodeURIComponent(item) + "=" + encodeURIComponent(fields[item].value)));
                }
            }
        }
        return data;
    }

    function trackData () {
        var data = collectData();
        if (data) {
            xhr = new XMLHttpRequest();
            xhr.open('POST', '/cartsguru/saveaccount', true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.send(data.join("&"));
        }
    }
})(document);
