<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_名前に基づいて検索が行われる()
    {
        $queryMock = Mockery::mock(Builder::class);
        $term = '山田';

        $queryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($term) {
            $subQueryMock = Mockery::mock(Builder::class);
            $subQueryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($term) {
                $subSubQueryMock = Mockery::mock(Builder::class);
                $subSubQueryMock->shouldReceive('where')
                    ->once()
                    ->with('name', 'like', "%{$term}%")
                    ->andReturnSelf();
                $subSubQueryMock->shouldReceive('orWhereRaw')
                    ->once()
                    ->with("REPLACE(REPLACE(phoneNumber, '-', ''), ' ', '') like ?", ["%{$term}%"])
                    ->andReturnSelf();
                $closure($subSubQueryMock);
                return true;
            }));
            $closure($subQueryMock);
            return true;
        }))->andReturnSelf();

        Customer::searchByTerm($queryMock, $term);

        $this->addToAssertionCount(1);
        Mockery::close();
    }

    public function test_電話番号に基づいて検索が行われる()
    {
        $queryMock = Mockery::mock(Builder::class);
        $term = '090-1234-5678';

        $queryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($term) {
            $subQueryMock = Mockery::mock(Builder::class);
            $subQueryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($term) {
                $subSubQueryMock = Mockery::mock(Builder::class);
                $subSubQueryMock->shouldReceive('where')
                    ->once()
                    ->with('name', 'like', "%{$term}%")
                    ->andReturnSelf();
                $subSubQueryMock->shouldReceive('orWhereRaw')
                    ->once()
                    ->with("REPLACE(REPLACE(phoneNumber, '-', ''), ' ', '') like ?", ["%{$term}%"])
                    ->andReturnSelf();
                $closure($subSubQueryMock);
                return true;
            }));
            $closure($subQueryMock);
            return true;
        }))->andReturnSelf();

        Customer::searchByTerm($queryMock, $term);

        $this->addToAssertionCount(1);
        Mockery::close();
    }

    public function test_名前と電話番号に基づいて検索が行われる()
    {
        $queryMock = Mockery::mock(Builder::class);
        $term = '山田 090-1234-5678';
        $terms = preg_split('/\s+/', $term);

        $queryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($terms) {
            $subQueryMock = Mockery::mock(Builder::class);
            foreach ($terms as $term) {
                $subQueryMock->shouldReceive('where')->once()->with(Mockery::on(function ($closure) use ($term) {
                    $subSubQueryMock = Mockery::mock(Builder::class);
                    $subSubQueryMock->shouldReceive('where')
                        ->once()
                        ->with('name', 'like', "%{$term}%")
                        ->andReturnSelf();
                    $subSubQueryMock->shouldReceive('orWhereRaw')
                        ->once()
                        ->with("REPLACE(REPLACE(phoneNumber, '-', ''), ' ', '') like ?", ["%{$term}%"])
                        ->andReturnSelf();
                    $closure($subSubQueryMock);
                    return true;
                }));
            }
            $closure($subQueryMock);
            return true;
        }))->andReturnSelf();

        Customer::searchByTerm($queryMock, $term);

        $this->addToAssertionCount(1);
        Mockery::close();
    }
}
