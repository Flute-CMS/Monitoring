<?php

namespace Flute\Modules\Monitoring\Services;

class FaceitRankService
{
    /**
     * Check if FaceitInfo module is available
     */
    public function isFaceitInfoAvailable(): bool
    {
        return class_exists('\Flute\Modules\FaceitInfo\Services\FaceitInfo');
    }

    /**
     * Get Faceit rank image URL for a player
     */
    public function getFaceitRankImage(int $skillLevel): ?string
    {
        if (!$this->isFaceitInfoAvailable()) {
            return null;
        }

        return asset("assets/img/ranks/faceit/{$skillLevel}.webp");
    }

    /**
     * Get Faceit player info
     */
    public function getFaceitPlayerInfo(int $steamId): ?array
    {
        if (!$this->isFaceitInfoAvailable()) {
            return null;
        }

        try {
            $faceitService = app()->get('\Flute\Modules\FaceitInfo\Services\FaceitInfo');
            $faceitData = $faceitService->getFaceitInfo($steamId);

            if (empty($faceitData['items']) || !isset($faceitData['items'][0])) {
                return null;
            }

            $player = $faceitData['items'][0];

            if (!isset($player['games']['cs2'])) {
                return null;
            }

            $cs2Data = $player['games']['cs2'];

            return [
                'skill_level' => $cs2Data['skill_level'] ?? 0,
                'faceit_elo' => $cs2Data['faceit_elo'] ?? 0,
                'skill_level_label' => $cs2Data['skill_level_label'] ?? '',
                'rank_image' => $this->getFaceitRankImage(1),
                'faceit_url' => $player['faceit_url'] ?? null,
                'nickname' => $player['nickname'] ?? null,
            ];
        } catch (\Exception $e) {
            logs('modules')->error('Failed to get Faceit player info: ' . $e->getMessage());
            return null;
        }
    }
}
