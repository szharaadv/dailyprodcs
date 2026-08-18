document.querySelectorAll('.filter-bar').forEach((form) => {
    form.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => form.submit());
    });
});
