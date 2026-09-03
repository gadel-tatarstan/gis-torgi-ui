<?php

use App\Models\Lot;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutMiddleware(PreventRequestForgery::class);
    });

test('removes a lot from the YouGile board when it is on the board', function () {
    $user = User::factory()->create();
    UserSetting::create([
        'user_id' => $user->id,
        'yg_api_token' => 'test-token',
    ]);
    $lot = Lot::create([
        'id' => 'lot-1',
        'notice_number' => 'notice-1',
        'lot_number' => 1,
        'lot_name' => 'Земельный участок',
        'price_min' => 100000,
        'on_board' => true,
        'yg_task_id' => 'yg-task-123',
    ]);

    Http::fake([
        'ru.yougile.com/api-v2/tasks/*' => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/lots/remove-from-yougile', ['id' => $lot->id])
        ->assertSuccessful()
        ->assertJson(['success' => true]);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && $request->url() === 'https://ru.yougile.com/api-v2/tasks/yg-task-123'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['deleted'] === true;
    });

    $lot->refresh();

    expect($lot->on_board)->toBeFalse()
        ->and($lot->yg_task_id)->toBeNull();
});

test('does not call the API when the lot is not on the board', function () {
    $user = User::factory()->create();
    UserSetting::create([
        'user_id' => $user->id,
        'yg_api_token' => 'test-token',
    ]);
    $lot = Lot::create([
        'id' => 'lot-1',
        'notice_number' => 'notice-1',
        'lot_number' => 1,
        'lot_name' => 'Земельный участок',
        'price_min' => 100000,
    ]);

    Http::fake();

    $this->postJson('/api/lots/remove-from-yougile', ['id' => $lot->id])
        ->assertStatus(422)
        ->assertJson(['error' => 'Лот не находится на доске']);

    Http::assertNothingSent();
});

test('returns an error when YouGile token is not configured', function () {
    $lot = Lot::create([
        'id' => 'lot-1',
        'notice_number' => 'notice-1',
        'lot_number' => 1,
        'lot_name' => 'Земельный участок',
        'price_min' => 100000,
        'on_board' => true,
        'yg_task_id' => 'yg-task-123',
    ]);

    Http::fake();

    $this->postJson('/api/lots/remove-from-yougile', ['id' => $lot->id])
        ->assertStatus(400)
        ->assertJson(['error' => 'Настройки YouGile не заполнены (токен обязателен)']);

    Http::assertNothingSent();
});

test('keeps the lot on the board when the YouGile API fails', function () {
    $user = User::factory()->create();
    UserSetting::create([
        'user_id' => $user->id,
        'yg_api_token' => 'test-token',
    ]);
    $lot = Lot::create([
        'id' => 'lot-1',
        'notice_number' => 'notice-1',
        'lot_number' => 1,
        'lot_name' => 'Земельный участок',
        'price_min' => 100000,
        'on_board' => true,
        'yg_task_id' => 'yg-task-123',
    ]);

    Http::fake([
        'ru.yougile.com/api-v2/tasks/*' => Http::response(['message' => 'Недостаточно прав'], 403),
    ]);

    $this->postJson('/api/lots/remove-from-yougile', ['id' => $lot->id])
        ->assertStatus(500)
        ->assertJson(['error' => 'Ошибка удаления карточки в YouGile']);

    $lot->refresh();

    expect($lot->on_board)->toBeTrue()
        ->and($lot->yg_task_id)->toBe('yg-task-123');
});
