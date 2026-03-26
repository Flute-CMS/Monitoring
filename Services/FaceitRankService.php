<?php

namespace Flute\Modules\Monitoring\Services;

use Exception;

class FaceitRankService
{
    public function isFaceitInfoAvailable(): bool
    {
        return class_exists('\Flute\Modules\FaceitInfo\Services\FaceitInfo')
            && !empty(config('faceit.api_key'))
            && !empty(config('faceit.game'));
    }

    public function getFaceitRankImage(int $skillLevel): ?string
    {
        if ($skillLevel < 1 || $skillLevel > 10) {
            return null;
        }

        return asset("assets/img/ranks/faceit/{$skillLevel}.svg");
    }

    /**
     * @param string $steamId Steam64 ID as string
     */
    public function getFaceitPlayerInfo(string $steamId): ?array
    {
        if (!$this->isFaceitInfoAvailable()) {
            return null;
        }

        try {
            $faceitService = app()->get('\Flute\Modules\FaceitInfo\Services\FaceitInfo');
            $faceitData = $faceitService->getFaceitInfo($steamId);

            if (empty($faceitData) || empty($faceitData['player_id'])) {
                return null;
            }

            $game = config('faceit.game', 'cs2');

            if (!isset($faceitData['games'][$game])) {
                return null;
            }

            $gameData = $faceitData['games'][$game];
            $skillLevel = (int) ($gameData['skill_level'] ?? 0);

            return [
                'skill_level' => $skillLevel,
                'faceit_elo' => (int) ($gameData['faceit_elo'] ?? 0),
                'skill_level_label' => $gameData['skill_level_label'] ?? '',
                'rank_image' => $this->getFaceitRankImage($skillLevel),
                'faceit_url' => $faceitData['faceit_url'] ?? null,
                'nickname' => $faceitData['nickname'] ?? null,
            ];
        } catch (Exception $e) {
            logs('modules')->warning('Failed to get Faceit player info: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Bulk fetch Faceit info for multiple Steam64 IDs.
     *
     * @param string[] $steamIds Steam64 IDs
     * @return array<string, array|null> Keyed by steam64
     */
    public function getBulkFaceitPlayerInfo(array $steamIds): array
    {
        if (!$this->isFaceitInfoAvailable() || empty($steamIds)) {
            return [];
        }

        try {
            $faceitService = app()->get('\Flute\Modules\FaceitInfo\Services\FaceitInfo');
            $bulkData = $faceitService->getBulkFaceitInfo($steamIds);
        } catch (Exception $e) {
            logs('modules')->warning('Failed to bulk fetch Faceit info: ' . $e->getMessage());

            return [];
        }

        $game = config('faceit.game', 'cs2');
        $result = [];

        foreach ($steamIds as $steamId) {
            $faceitData = $bulkData[$steamId] ?? [];

            if (empty($faceitData) || empty($faceitData['player_id']) || !isset($faceitData['games'][$game])) {
                $result[$steamId] = null;

                continue;
            }

            $gameData = $faceitData['games'][$game];
            $skillLevel = (int) ($gameData['skill_level'] ?? 0);

            $result[$steamId] = [
                'skill_level' => $skillLevel,
                'faceit_elo' => (int) ($gameData['faceit_elo'] ?? 0),
                'skill_level_label' => $gameData['skill_level_label'] ?? '',
                'rank_image' => $this->getFaceitRankImage($skillLevel),
                'faceit_url' => $faceitData['faceit_url'] ?? null,
                'nickname' => $faceitData['nickname'] ?? null,
            ];
        }

        return $result;
    }
}
