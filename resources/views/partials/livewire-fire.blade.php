{{-- ─────────────────────────────────────────────────────────────────
     Tiny shim so inline `onclick` handlers can fire events at a
     sibling Livewire component.

     Livewire v4 dropped the v2/v3 `Livewire.dispatch()` global. v4
     registers `$listeners` on the component's own root DOM element
     (`component.el`), not on `window`, so a naive
     `Livewire.dispatch('foo', params)` silently does nothing and
     nothing reaches the server.

     `livewireFire(componentName, eventName, params)` looks up the
     target component by its `wire:name` attribute and dispatches a
     bubbling CustomEvent directly on its root element, which is
     exactly what `listen2()` (the v4 listener installer) is
     listening for.

     Usage from a Blade view:
       onclick="livewireFire('delete-production-modal', 'openDeleteModal', { id: 5 })"

     Note: `componentName` is the kebab/camel-cased Livewire class
     basename — i.e. `App\Livewire\DeleteProductionModal` →
     `delete-production-modal`. Must match the `wire:name` rendered
     on the component's root element.
     ───────────────────────────────────────────────────────────────── --}}
<script>
    window.livewireFire = function (componentName, eventName, params) {
        const root = document.querySelector('[wire\\:name="' + componentName + '"]');
        if (!root) {
            console.warn('[livewireFire] no Livewire component found for name: ' + componentName);
            return;
        }
        root.dispatchEvent(new CustomEvent(eventName, {
            bubbles: true,
            detail: params || {},
        }));
    };
</script>