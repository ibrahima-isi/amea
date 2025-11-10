document.addEventListener('DOMContentLoaded', function () {
    const sliderImageModalEl = document.getElementById('sliderImageModal');
    if (!sliderImageModalEl) return;

    const sliderImageModal = new bootstrap.Modal(sliderImageModalEl);
    const modalTitle = document.getElementById('sliderImageModalLabel');
    const form = document.getElementById('sliderImageForm');
    const imageIdInput = form.querySelector('input[name="image_id"]');
    const actionInput = form.querySelector('input[name="action"]');
    const titleInput = form.querySelector('#title');
    const captionInput = form.querySelector('#caption');
    const displayOrderInput = form.querySelector('#display_order');
    const isActiveSelect = form.querySelector('#is_active');
    const imageFileInput = form.querySelector('#image_file');

    // Handle modal opening for "Add"
    sliderImageModalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        // If the button that triggered the modal is not the edit button
        if (!button.classList.contains('btn-edit-slider')) {
            modalTitle.textContent = 'Ajouter une image';
            actionInput.value = 'add';
            imageIdInput.value = '';
            form.reset();
            imageFileInput.required = true;
        }
    });

    // Handle "Edit" button clicks
    document.querySelectorAll('.btn-edit-slider').forEach(button => {
        button.addEventListener('click', function () {
            modalTitle.textContent = 'Modifier l\'image';
            actionInput.value = 'edit';
            imageIdInput.value = this.dataset.id;
            titleInput.value = this.dataset.title;
            captionInput.value = this.dataset.caption;
            displayOrderInput.value = this.dataset.order;
            isActiveSelect.value = this.dataset.active;
            imageFileInput.required = false;
            sliderImageModal.show();
        });
    });

    // Handle "Delete" button clicks with SweetAlert confirmation
    document.querySelectorAll('.btn-delete-slider').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action est irréversible!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
