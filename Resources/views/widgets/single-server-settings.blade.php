<form>
    <x-forms.field class="mb-4">
        <x-forms.label for="server_id">{{ __('monitoring.server.details') }}</x-forms.label>
        <x-fields.select name="server_id" id="server_id">
            <option value="0">{{ __('monitoring.server.select_server') }}</option>
            @foreach($servers as $serverData)
                <option value="{{ $serverData['server']->id }}" @if(($settings['server_id'] ?? 0) == $serverData['server']->id) selected @endif>
                    {{ $serverData['server']->name }}
                </option>
            @endforeach
        </x-fields.select>
        <x-fields.small>{{ __('monitoring.single_server.select_server_help') }}</x-fields.small>
    </x-forms.field>

    <x-forms.field class="mb-4">
        <x-forms.label for="display_mode">{{ __('monitoring.settings.display_mode') }}</x-forms.label>
        <x-fields.select id="display_mode" name="display_mode">
            <option value="standard" {{ ($settings['display_mode'] ?? 'standard') == 'standard' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_standard') }}</option>
            <option value="compact" {{ ($settings['display_mode'] ?? '') == 'compact' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_compact') }}</option>
            <option value="ultracompact" {{ ($settings['display_mode'] ?? '') == 'ultracompact' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_ultracompact') }}</option>
        </x-fields.select>
        <x-fields.small>{{ __('monitoring.settings.display_mode_help') }}</x-fields.small>
    </x-forms.field>

    <x-forms.field class="mb-4" style="max-width: 200px;">
        <x-fields.checkbox name="hide_modal" id="hide_modal" :checked="$settings['hide_modal'] ?? false"
            label="{{ __('monitoring.single_server.hide_modal') }}" />
        <x-fields.small>{{ __('monitoring.single_server.hide_modal_help') }}</x-fields.small>
    </x-forms.field>
</form> 