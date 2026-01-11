document.addEventListener('DOMContentLoaded', function() {
    // === Tagify Initialization for Nationalities ===
    const input = document.querySelector('input[name="nationalites"]');
    if (input) {
        fetch('assets/json/countries.json')
            .then(response => response.json())
            .then(countries => {
                new Tagify(input, {
                    whitelist: countries,
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,
                        classname: "tags-look",
                        enabled: 0,
                        closeOnSelect: false
                    }
                });
            });
    }
});
