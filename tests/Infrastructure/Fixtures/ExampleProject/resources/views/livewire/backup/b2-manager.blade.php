<div>
    <h1>B2 Manager</h1>

    <button wire:click="refreshBackups">Refresh</button>
    <button wire:click="deleteBackup({{ $backup->id }})">Delete</button>

    <form wire:submit.prevent="save">
        <input type="text" wire:model="name">
        <button type="submit">Save</button>
    </form>

    <button wire:click="refreshBackups">Refresh Again</button>
</div>
