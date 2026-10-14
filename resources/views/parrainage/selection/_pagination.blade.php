{{-- Pagination d'une liste de la session. Variables : $liste (paginateur) --}}
@if ($liste->hasPages())
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <small class="text-muted">OEV {{ $liste->firstItem() }} à {{ $liste->lastItem() }} sur {{ $liste->total() }}</small>
    {{ $liste->links('pagination::bootstrap-5') }}
  </div>
@endif
