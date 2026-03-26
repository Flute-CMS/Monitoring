<div class="server-details-skeleton">
    <div class="skeleton-left">
        <div class="skeleton skeleton-bg"></div>
        <div class="skeleton-left-content">
            <div class="skeleton skeleton-title-lg"></div>
            <div class="skeleton skeleton-subtitle"></div>
            <div class="skeleton-pills">
                <div class="skeleton skeleton-pill"></div>
                <div class="skeleton skeleton-pill skeleton-pill--sm"></div>
                <div class="skeleton skeleton-pill"></div>
            </div>
            <div class="skeleton skeleton-ip"></div>
            <div class="skeleton skeleton-btn"></div>
        </div>
    </div>

    <div class="skeleton-right">
        <div class="skeleton-right-header">
            <div class="skeleton skeleton-title"></div>
            <div class="skeleton skeleton-search"></div>
        </div>
        <div class="skeleton-right-rows">
            @for ($i = 0; $i < 8; $i++)
                <div class="skeleton-row">
                    <div class="skeleton skeleton-avatar"></div>
                    <div class="skeleton skeleton-name" style="width: {{ rand(80, 150) }}px"></div>
                    <div class="skeleton skeleton-stat"></div>
                    <div class="skeleton skeleton-stat skeleton-stat--sm"></div>
                    <div class="skeleton skeleton-stat skeleton-stat--sm"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
