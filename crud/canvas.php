<!-- Offcanvas Panel -->
<div class="offcanvas offcanvas-end" id="crudCanvas" tabindex="-1" aria-labelledby="crudCanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="crudCanvasLabel">Manage</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="crudForm" class="offcanvas-body needs-validation" novalidate>
        <div id="formFields"></div>
        <div class="mt-4 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">
                <i class="fa fa-share"></i> Cancel
            </button>
        </div>
    </form>
</div>
<div class="offcanvas offcanvas-start" id="crudFilter" tabindex="-1" aria-labelledby="crudFilterLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="crudFilterLabel">Filtration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body bg-dark"><div id="filterBar" class="mb-3 d-flex flex-wrap gap-2"></div></div>
    
</div>