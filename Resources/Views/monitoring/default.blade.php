<div class="monitoring_container">
    <h3 class="mb-2">@t('monitoring.our_servers')</h3>

    <div class="row gx-4 gy-4">
        @foreach ($servers as $item)
            <div class="col-md-4">
                <div class="monitoring_card">
                    <!-- <img src="@asset($item['info']['Map_img'])"alt="{{ $item['serverName'] }}"> -->
                    <img src="{{$item['info']['Map_img']}}" onerror="this.src='assets/img/maps/{{ $item['serverMod'] }}/-.webp';"  alt="{{ $item['serverName'] }}">

                    <div class="monitoring_card-content">
                        <div>
                            @if ($item['status'] === 'offline')
                                @t('monitoring.info.server_is_shutdown')
                            @else
                                {{ $item['serverName'] }}
							<br>
                                {{ $item['serverHostName'] }}
                            @endif
                        </div>
                        <div>
                            <img src="@asset($item['info']['Map_pin'])">
                            @if (__($item['info']['Map']) !== $item['info']['Map'])
                                {{ __($item['info']['Map']) }}
                            @else
                                {{ $item['info']['Map'] }}
                            @endif
                        </div>
                    </div>

                    <a href="steam://connect/{{ $item['ip'] }}:{{ $item['port'] }}" class="monitoring_card-playbtn">
                        <i class="ph ph-play play"></i>
                        <i class="ph ph-arrow-up-right arrow"></i>
                    </a>

                    <div class="monitoring_card-footer">
                        <div class="monitoring_card-footer-text">
                            <p id="{{ $item['id'] }}" data-copy="{{ $item['ip'] }}:{{ $item['port'] }}"
                                data-tooltip="@t('monitoring.copy_ip.description')" data-tooltip-conf="right">
                                <i class="ph ph-copy"></i>
                                {{ $item['displayIp'] }}
                            </p>
                            <p class="monitoring_card-footer-players"
                                @if ($item['info']['Players'] !== '-') onclick="showInfoModal('{{ $item['id'] }}')" data-tooltip="@t('monitoring.open_info')" data-tooltip-conf="left" @endif>
                                <i class="ph ph-users"></i>
                                {{ $item['info']['Players'] }}/{{ $item['info']['MaxPlayers'] }}
                            </p>
                        </div>

                        <div class="monitoring_card-footer-progress">
                            <div style="width: {{ $item['info']['percentOnline']['percent'] }}%"
                                class="{{ $item['info']['percentOnline']['name'] }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @component(mm('Source_Monitoring', 'Resources/Views/components/info.blade.php'))
    @endcomponent
</div>
