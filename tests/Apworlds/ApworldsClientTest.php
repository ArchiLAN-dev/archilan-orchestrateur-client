<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\Apworlds;

use Archilan\OrchestratorClient\Apworlds\ApworldsClient;
use Archilan\OrchestratorClient\Apworlds\Response\ApworldPreflight;
use Archilan\OrchestratorClient\Apworlds\Response\ChoiceTemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\DictTemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\RangeTemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\TemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\TextTemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\ToggleTemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\UploadApworldResult;
use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ApworldsClientTest extends TestCase
{
    private function client(MockResponse $response): ApworldsClient
    {
        $transport = new HttpTransport(new MockHttpClient($response), 'http://localhost:8000', 'key');

        return new ApworldsClient($transport);
    }

    private function uploadBody(string $hash, mixed $options = []): string
    {
        return json_encode(['hash' => $hash, 'options' => $options]) ?: '';
    }

    public function testUpload_returnsUploadApworldResult(): void
    {
        $body = $this->uploadBody('deadbeef', [
            ['key' => 'logic_percent', 'description' => 'Controls logic.', 'type' => 'range',
             'defaultValue' => 80, 'rangeMin' => 50, 'rangeMax' => 95],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $this->assertInstanceOf(UploadApworldResult::class, $result);
        $this->assertSame('deadbeef', $result->hash);
        $this->assertCount(1, $result->options);

        $opt = $result->options[0];
        $this->assertInstanceOf(RangeTemplateOption::class, $opt);
        $this->assertSame('logic_percent', $opt->key);
        $this->assertSame(80, $opt->default);
        $this->assertSame(50, $opt->rangeMin);
        $this->assertSame(95, $opt->rangeMax);
    }

    /**
     * An OptionDict is a mapping of named settings to literal values, and it looks exactly like a
     * weighted option on the wire. Reading it as one coerces every value to an integer, which turned
     * `default_player_name: player_name` into `default_player_name: 0` and crashed generation.
     */
    public function testUpload_dictOption(): void
    {
        $body = $this->uploadBody('d1c7', [
            ['key' => 'game_options', 'description' => 'In-game settings.', 'type' => 'dict',
             'defaultValue' => [
                 'default_player_name' => 'player_name',
                 'text_speed' => 'fast',
                 'turbo' => true,
                 'starter' => 4,
             ],
             'validKeys' => ['default_player_name', 'text_speed', 'turbo', 'starter', 7]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(DictTemplateOption::class, $opt);
        $this->assertSame('game_options', $opt->key);
        // Every literal survives with its own type - no weight coercion anywhere.
        $this->assertSame([
            'default_player_name' => 'player_name',
            'text_speed' => 'fast',
            'turbo' => true,
            'starter' => 4,
        ], $opt->defaults);
        $this->assertSame(['default_player_name', 'text_speed', 'turbo', 'starter'], $opt->validKeys);
    }

    /** A sub-setting can itself be a block (Slay the Spire's `advanced_characters`). */
    public function testUpload_dictOptionKeepsNestedBlocks(): void
    {
        $body = $this->uploadBody('d1c8', [
            ['key' => 'advanced_characters', 'description' => 'Per-character settings.', 'type' => 'dict',
             'defaultValue' => ['Ironclad' => ['ascension' => 3, 'enabled' => true]]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));

        $opt = $client->upload('binary-data', 'game.apworld')->options[0];

        $this->assertInstanceOf(DictTemplateOption::class, $opt);
        $this->assertSame(['Ironclad' => ['ascension' => 3, 'enabled' => true]], $opt->defaults);
        $this->assertSame([], $opt->validKeys);
    }

    /**
     * Story 9.51: a world that declares a `schema` for its OptionDict also says what each
     * sub-setting accepts. Only the sub-settings it actually declares get an entry - the rest
     * must stay absent, because an empty entry reads as "declared, and empty".
     */
    public function testUpload_dictOptionSubValues(): void
    {
        $body = $this->uploadBody('d1c9', [
            ['key' => 'game_options', 'description' => 'In-game settings.', 'type' => 'dict',
             'defaultValue' => ['battle_style' => 'shift', 'player_name' => 'AP'],
             'validKeys' => ['battle_style', 'player_name'],
             'keys' => [
                 'battle_style' => ['values' => ['shift', 'set']],
                 'text_frame' => ['values' => ['1', 2, '3']],
                 'thinned' => ['values' => ['only', 4, null]],
                 'sound' => ['values' => ['stereo']],
                 'gender' => ['values' => []],
                 'broken' => ['values' => 'not-a-list'],
                 'empty' => [],
             ]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));

        $opt = $client->upload('binary-data', 'game.apworld')->options[0];

        $this->assertInstanceOf(DictTemplateOption::class, $opt);
        $this->assertSame(['shift', 'set'], $opt->keys['battle_style']->values);
        // Non-strings are dropped, and what is left is re-indexed as a list.
        $this->assertSame(['1', '3'], $opt->keys['text_frame']->values);
        // Thinned down to a single survivor, it stops being a choice and disappears entirely -
        // half a vocabulary in a dropdown is worse than none.
        $this->assertArrayNotHasKey('thinned', $opt->keys);
        // A single value is not a choice; neither is an empty or malformed one.
        $this->assertArrayNotHasKey('sound', $opt->keys);
        $this->assertArrayNotHasKey('gender', $opt->keys);
        $this->assertArrayNotHasKey('broken', $opt->keys);
        $this->assertArrayNotHasKey('empty', $opt->keys);
        // A sub-setting the schema says nothing about carries nothing.
        $this->assertArrayNotHasKey('player_name', $opt->keys);
    }

    /** A world that declares no schema carries no sub-option values at all. */
    public function testUpload_dictOptionWithoutSchemaHasNoSubValues(): void
    {
        $body = $this->uploadBody('d1ca', [
            ['key' => 'game_options', 'description' => 'In-game settings.', 'type' => 'dict',
             'defaultValue' => ['battle_style' => 'shift'], 'validKeys' => ['battle_style']],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));

        $opt = $client->upload('binary-data', 'game.apworld')->options[0];

        $this->assertInstanceOf(DictTemplateOption::class, $opt);
        $this->assertSame([], $opt->keys);
    }

    public function testUpload_choiceOption(): void
    {
        $body = $this->uploadBody('abc123', [
            ['key' => 'smallkey_shuffle', 'description' => 'Where keys go.',
             'type' => 'choice', 'defaultValue' => 'original_dungeon',
             'validValues' => ['original_dungeon', 'any_world', 'own_world'],
             'weights' => ['original_dungeon' => 50, 'any_world' => 0, 'own_world' => 0]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(ChoiceTemplateOption::class, $opt);
        $this->assertSame('original_dungeon', $opt->default);
        $this->assertSame(['original_dungeon', 'any_world', 'own_world'], $opt->validValues);
        $this->assertSame(['original_dungeon' => 50, 'any_world' => 0, 'own_world' => 0], $opt->weights);
    }

    public function testUpload_toggleOption(): void
    {
        $body = $this->uploadBody('abc123', [
            ['key' => 'swordless', 'description' => 'No swords.', 'type' => 'toggle',
             'defaultValue' => false, 'weights' => ['false' => 50, 'true' => 0]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(ToggleTemplateOption::class, $opt);
        $this->assertFalse($opt->default);
        $this->assertSame(['false' => 50, 'true' => 0], $opt->weights);
    }

    public function testUpload_emptyOptions(): void
    {
        $body = $this->uploadBody('abc123', []);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $this->assertSame([], $result->options);
    }

    public function testUpload_sendsMultipartWithFile(): void
    {
        $capturedBody = '';
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('/apworlds', $url);
            $capturedBody = $options['body'] ?? '';

            return new MockResponse($this->uploadBody('abc', []) ?: '', ['http_code' => 201]);
        });
        $client = new ApworldsClient(new HttpTransport($mock, 'http://localhost:8000', 'key'));
        $client->upload('file-binary-content', 'zelda.apworld');

        $this->assertIsString($capturedBody);
        $this->assertStringContainsString('zelda.apworld', $capturedBody);
    }

    public function testUpload_toggleTrueDefault(): void
    {
        $body = $this->uploadBody('abc123', [
            ['key' => 'disable_forced_camera', 'description' => 'Lock camera.', 'type' => 'toggle',
             'defaultValue' => true, 'weights' => ['false' => 0, 'true' => 50]],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(ToggleTemplateOption::class, $opt);
        $this->assertTrue($opt->default);
        $this->assertSame(['false' => 0, 'true' => 50], $opt->weights);
    }

    public function testUpload_choiceOption_noWeightsField_defaultsToEmpty(): void
    {
        $body = $this->uploadBody('abc123', [
            ['key' => 'some_choice', 'description' => 'Desc.', 'type' => 'choice',
             'defaultValue' => 'a', 'validValues' => ['a', 'b']],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(ChoiceTemplateOption::class, $opt);
        $this->assertSame([], $opt->weights);
    }

    public function testUpload_rangeNullDefault(): void
    {
        // Ranges like puzzle_randomization_seed have no concrete default
        $body = $this->uploadBody('abc123', [
            ['key' => 'puzzle_randomization_seed', 'description' => 'Seed value.',
             'type' => 'range', 'defaultValue' => null, 'rangeMin' => 1, 'rangeMax' => 9999999],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(RangeTemplateOption::class, $opt);
        $this->assertNull($opt->default);
        $this->assertSame(1, $opt->rangeMin);
        $this->assertSame(9999999, $opt->rangeMax);
    }

    public function testUpload_textOption(): void
    {
        // Text options have no structured default/values (e.g. locked_items, excluded_items)
        $body = $this->uploadBody('abc123', [
            ['key' => 'locked_items', 'description' => 'Guaranteed unlockable items.',
             'type' => 'text', 'defaultValue' => null],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(TextTemplateOption::class, $opt);
        $this->assertNull($opt->default);
    }

    public function testUpload_choiceWithRandomAsOption(): void
    {
        // Options like game_version or starting_robot_master include "random" as a selectable value
        $body = $this->uploadBody('abc123', [
            ['key' => 'game_version', 'description' => 'Red or Blue.',
             'type' => 'choice', 'defaultValue' => 'random',
             'validValues' => ['red', 'blue', 'random']],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertInstanceOf(ChoiceTemplateOption::class, $opt);
        $this->assertSame('random', $opt->default);
        $this->assertSame(['red', 'blue', 'random'], $opt->validValues);
    }

    public function testUpload_multilineDescription(): void
    {
        $desc = "First line of description.\nSecond line with more detail.";
        $body = $this->uploadBody('abc123', [
            ['key' => 'mission_order', 'description' => $desc,
             'type' => 'choice', 'defaultValue' => 'golden_path',
             'validValues' => ['vanilla', 'golden_path', 'mini_campaign']],
        ]);
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $opt = $result->options[0];
        $this->assertSame($desc, $opt->description);
    }

    public function testUpload_choiceOption_hasNoRangeFields(): void
    {
        // A ChoiceTemplateOption structurally cannot have range fields - verified by instanceof
        $body = json_encode(['hash' => 'abc', 'options' => [
            ['key' => 'some_option', 'description' => 'Desc.', 'type' => 'choice',
             'defaultValue' => 'foo', 'validValues' => ['foo', 'bar']],
        ]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 201]));
        $result = $client->upload('binary-data', 'game.apworld');

        $this->assertInstanceOf(ChoiceTemplateOption::class, $result->options[0]);
    }

    public function testGetYamlTemplate_returnsRawYaml(): void
    {
        $yaml = "name: Zelda\nversion: 1\n";
        $client = $this->client(new MockResponse($yaml, ['http_code' => 200]));
        $result = $client->getYamlTemplate('deadbeef');

        $this->assertSame($yaml, $result);
    }

    public function testGetLocations_returnsList(): void
    {
        $body = json_encode(['locations' => ['Boss Reward', 'Chest 1', 'Chest 2']]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $this->assertSame(['Boss Reward', 'Chest 1', 'Chest 2'], $client->getLocations('deadbeef'));
    }

    public function testGetLocations_emptyWhenNotIntrospected(): void
    {
        // A sidecar with only option types (no "locations" key) yields an empty list.
        $body = json_encode(['options' => [['key' => 'foo', 'type' => 'choice']]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $this->assertSame([], $client->getLocations('deadbeef'));
    }

    public function testGetLocations_filtersNonStringEntries(): void
    {
        $body = json_encode(['locations' => ['A', 123, null, 'B', ['nested']]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $this->assertSame(['A', 'B'], $client->getLocations('deadbeef'));
    }

    public function testList_parsesPreflightVerdict(): void
    {
        $body = json_encode(['apworlds' => [
            ['hash' => 'aaa', 'game' => 'Game A', 'preflight' => [
                'status' => 'failed', 'error' => 'Exception: boom', 'checkedAt' => '2026-07-30T12:00:00Z', 'overridden' => true,
            ]],
            ['hash' => 'bbb', 'game' => 'Game B'],
        ]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $entries = $client->list();

        $this->assertCount(2, $entries);
        $preflight = $entries[0]->preflight;
        $this->assertNotNull($preflight);
        $this->assertSame(ApworldPreflight::STATUS_FAILED, $preflight->status);
        $this->assertSame('Exception: boom', $preflight->error);
        $this->assertSame('2026-07-30T12:00:00Z', $preflight->checkedAt);
        $this->assertTrue($preflight->overridden);
        $this->assertFalse($preflight->blocksUsage());
        $this->assertNull($entries[1]->preflight);
    }

    /** Story 38.8: a verdict names the Archipelago image that produced it. */
    public function testPreflight_readsImageAndImageIdWhenPresent(): void
    {
        $preflight = ApworldPreflight::fromArray([
            'status' => 'passed', 'image' => 'ghcr.io/archilan-dev/archipelago:0.16.1', 'imageId' => 'sha256:abc123',
        ]);

        $this->assertSame('ghcr.io/archilan-dev/archipelago:0.16.1', $preflight->image);
        $this->assertSame('sha256:abc123', $preflight->imageId);
    }

    public function testPreflight_imageIsNullForALegacyVerdict(): void
    {
        // The orchestrator omits empty fields: an old verdict has no image key at all.
        $preflight = ApworldPreflight::fromArray(['status' => 'passed', 'checkedAt' => '2026-07-01T10:00:00Z']);

        $this->assertNull($preflight->image);
        $this->assertNull($preflight->imageId);
    }

    public function testPreflight_anInspectionThatFailedKeepsTheReferenceWithoutId(): void
    {
        $preflight = ApworldPreflight::fromArray(['status' => 'passed', 'image' => 'archipelago:latest', 'imageId' => '']);

        $this->assertSame('archipelago:latest', $preflight->image);
        $this->assertNull($preflight->imageId);
    }

    public function testBlocksUsage_onlyForFailedNonOverridden(): void
    {
        $this->assertTrue((new ApworldPreflight(status: ApworldPreflight::STATUS_FAILED))->blocksUsage());
        $this->assertFalse((new ApworldPreflight(status: ApworldPreflight::STATUS_FAILED, overridden: true))->blocksUsage());
        $this->assertFalse((new ApworldPreflight(status: ApworldPreflight::STATUS_PASSED))->blocksUsage());
        $this->assertFalse((new ApworldPreflight(status: ApworldPreflight::STATUS_PENDING))->blocksUsage());
        $this->assertFalse((new ApworldPreflight(status: ApworldPreflight::STATUS_SKIPPED))->blocksUsage());
    }

    public function testSetYamlTemplate_returnsTheStoredTemplate(): void
    {
        $body = json_encode(['hash' => 'aaa', 'template' => "name: Player{number}\ngame: Atlyss\n"]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $stored = $client->setYamlTemplate('aaa', "name: Player{number}\ngame: Atlyss\n");

        $this->assertSame("name: Player{number}\ngame: Atlyss\n", $stored);
    }

    public function testSetYamlTemplate_fallsBackToTheSentTemplate(): void
    {
        // Older orchestrator answering without echoing the template back.
        $client = $this->client(new MockResponse(json_encode(['hash' => 'aaa']) ?: '', ['http_code' => 200]));

        $this->assertSame('game: X', $client->setYamlTemplate('aaa', 'game: X'));
    }

    public function testRegenerateYamlTemplate_returnsTheFreshTemplate(): void
    {
        $body = json_encode(['hash' => 'aaa', 'template' => "game: Atlyss\nAtlyss: {}\n"]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $this->assertSame("game: Atlyss\nAtlyss: {}\n", $client->regenerateYamlTemplate('aaa'));
    }

    public function testRegenerateYamlTemplate_throwsWhenTheWorldCannotProduceOne(): void
    {
        $client = $this->client(new MockResponse(json_encode(['error' => 'regenerate template: boom']) ?: '', ['http_code' => 422]));

        $this->expectException(OrchestratorException::class);
        $client->regenerateYamlTemplate('aaa');
    }

    public function testReintrospect_acceptsTheAcknowledgement(): void
    {
        $body = json_encode(['hash' => 'aaa', 'introspected' => true]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        // Nothing is returned on purpose: the parsed options are served by getOptions(), which
        // merges them with the template. A second, subtly different shape of the same thing here
        // would only invite callers to pick the wrong one.
        $client->reintrospect('aaa');

        $this->expectNotToPerformAssertions();
    }

    public function testReintrospect_throwsWhenTheWorldCannotBeIntrospected(): void
    {
        // 422 means the sidecar was left untouched. It carries the range bounds, the option types
        // and the location list, so a caller that swallowed this would keep serving stale data
        // while believing it had refreshed.
        $client = $this->client(new MockResponse(json_encode(['error' => 'reintrospect options: boom']) ?: '', ['http_code' => 422]));

        $this->expectException(OrchestratorException::class);
        $client->reintrospect('aaa');
    }

    public function testRunPreflight_returnsPendingVerdict(): void
    {
        $body = json_encode(['hash' => 'aaa', 'preflight' => ['status' => 'pending', 'overridden' => false]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 202]));

        $verdict = $client->runPreflight('aaa');

        $this->assertSame(ApworldPreflight::STATUS_PENDING, $verdict->status);
    }

    public function testOverridePreflight_returnsUpdatedVerdict(): void
    {
        $body = json_encode(['hash' => 'aaa', 'preflight' => [
            'status' => 'failed', 'error' => 'Exception: boom', 'overridden' => true,
        ]]) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $verdict = $client->overridePreflight('aaa', true);

        $this->assertTrue($verdict->overridden);
        $this->assertFalse($verdict->blocksUsage());
    }
}
