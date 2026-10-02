<?php

namespace Tests\Differential;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ask;
use Tests\Support\Models\AddressModel;
use Tests\Support\Models\FavoriteModel;
use Tests\Support\Models\GroupModel;
use Tests\Support\Models\OrderLineModel;
use Tests\Support\Models\OrderModel;
use Tests\Support\Models\ProductModel;
use Tests\Support\Models\UserModel;
use Tests\Support\Models\WorkspaceModel;

/**
 * As an admin, whom no rule narrows: the candidate must answer exactly as RestExtension does -
 * the same rows, in the same order, with the same content and the same count, and the same
 * included relations.
 */
final class RelationsTest extends DifferentialTestCase
{
    private const FILTERS = [
        OrderModel::class => [
            'buyer_workspace.name:Alpha', 'buyer_workspace.name:-Alpha', 'buyer_workspace.name:null', 'buyer_workspace.name:-null',
            'seller_workspace.name:Beta', 'buyer_workspace.name:~a', 'buyer_workspace.id:[1,2]', 'seller_workspace.id:-1',
            'shipping_address.city:Aarhus', 'invoice_address.city:Aarhus', 'shipping_address.country_code:null',
            'parent.reference:A-1', 'parent.reference:null', 'child.reference:B-2', 'child.total:>50',
            'order_line.product_no:P-100', 'order_line.quantity:>1', 'order_line.quantity:null', 'order_line.product.name:Cherry',
            'buyer_workspace.group.name:Jutland', 'buyer_workspace.group.parent.name:Denmark',
            'buyer_workspace.user.email:ann@a.dk', 'buyer_workspace.users_workspace.status:pending',
            'total:>50,buyer_workspace.name:Alpha', 'buyer_workspace.name:~a,reference:~G', 'buyer_workspace.name:Alpha,seller_workspace.name:Beta',
            'buyer_workspace.name:~[lph,amm]', 'buyer_workspace.name:~a,seller_workspace.name:~e', 'buyer_workspace.group.name:null',
            'buyer_workspace.user.email:null', 'order_line.product.name:null', 'buyer_workspace.name:[Alpha,Gamma]', 'buyer_workspace.name:-[Alpha,Beta]',
        ],
        WorkspaceModel::class => [
            'user.email:ann@a.dk', 'user.email:null', 'user.age:>=30', 'user.email:-ann@a.dk',
            'users_workspace.status:pending', 'users_workspace.invited_by.email:ann@a.dk',
            'buyer_order.reference:A-1', 'seller_order.total:>50', 'buyer_order.total:null',
            'target_product.name:Apple', 'target_product.name:null', 'product.name:Banana',
            'group.name:Denmark', 'group.parent.name:Nordic', 'address.city:Hidden', 'invoice_address.country_code:DK', 'address.city:null',
            'user.email:~a.dk', 'user.email:~ann,name:~eta', 'users_workspace.status:null', 'target_product.workspace.name:null',
        ],
        UserModel::class => [
            'workspace.name:Alpha', 'workspace.name:null', 'workspace.name:-Alpha', 'users_workspace.status:approved',
            'users_workspace_invited_by.status:pending', 'favorite.product.name:Apple', 'favorite.product_no:P-999', 'workspace.group.name:Jutland',
            'favorite.product_no:null', 'favorite.product.name:null', 'workspace.group.parent.name:Denmark', 'workspace.name:-null',
        ],
        ProductModel::class => [
            'target_workspace.name:Alpha', 'target_workspace.name:null', 'favorite_product.user.email:ann@a.dk', 'order_line.quantity:>1', 'workspace.name:Beta',
        ],
        GroupModel::class => [
            'parent.name:Nordic', 'parent.name:null', 'child.name:Jutland', 'child.name:null', 'workspace.name:Gamma', 'parent.parent.name:Nordic',
        ],
        AddressModel::class => [
            'invoice_order.reference:A-1', 'shipping_order.reference:A-1', 'workspace.name:Alpha', 'invoice_workspace.name:Alpha',
        ],
        OrderLineModel::class => [
            'order.reference:G-9', 'product.name:Apple', 'product.name:null', 'order.buyer_workspace.name:Gamma',
        ],
        FavoriteModel::class => [
            'product.name:Cherry', 'user.email:null', 'product.workspace.name:Gamma',
        ],
    ];

    private const INCLUDES = [
        OrderModel::class => [
            'count_lines', 'buyer_workspace', 'seller_workspace', 'shipping_address', 'invoice_address', 'parent', 'child', 'order_line', 'buyer_workspace.group',
            'buyer_workspace.group.parent', 'buyer_workspace?include=group', 'buyer_workspace,seller_workspace,parent', 'order_line?limit=1', 'order_line?ordering=quantity:desc',
            'order_line?filter=quantity:>1', 'child?include=buyer_workspace', 'buyer_workspace.user', 'count_lines,order_line,buyer_workspace',
        ],
        WorkspaceModel::class => [
            'count_users', 'count_users,user', 'user', 'users_workspace', 'target_product', 'address', 'invoice_address', 'buyer_order',
            'user?ordering=email:desc', 'user?filter=age:>=30', 'users_workspace?include=invited_by', 'group.parent', 'seller_order?include=buyer_workspace',
        ],
        UserModel::class => ['workspace', 'favorite', 'favorite?include=product', 'users_workspace_invited_by', 'workspace?include=group'],
        ProductModel::class => ['target_workspace', 'favorite_product', 'workspace', 'order_line', 'target_workspace?ordering=name:desc'],
        GroupModel::class => ['parent', 'child'],
        OrderLineModel::class => ['product', 'order'],
        FavoriteModel::class => ['product'],
    ];

    private const ORDERINGS = [
        OrderModel::class => ['buyer_workspace.name:asc,id:asc', 'buyer_workspace.group.name:desc,id:asc', 'shipping_address.city:asc,id:desc', 'parent.reference:desc,id:asc'],
        WorkspaceModel::class => ['group.parent.name:asc,id:asc', 'invoice_address.country_code:desc,id:asc'],
        OrderLineModel::class => ['product.name:asc,id:asc', 'order.buyer_workspace.name:desc,id:desc'],
        GroupModel::class => ['parent.name:asc,id:asc'],
    ];

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function orderings(): iterable
    {
        foreach (self::ORDERINGS as $model => $orderings) {
            foreach ($orderings as $ordering) {
                yield self::short($model) . " ordering={$ordering}" => [$model, $ordering];
            }
        }
    }

    #[DataProvider('orderings')]
    public function testTheSameOrder(string $model, string $ordering): void
    {
        [$reference, $candidate] = self::both(static fn () => Ask::ids($model, '', null, $ordering));

        $this->assertSame($reference, $candidate, self::short($model) . " ordering={$ordering}");
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function filters(): iterable
    {
        foreach (self::FILTERS as $model => $filters) {
            foreach ($filters as $filter) {
                yield self::short($model) . " {$filter}" => [$model, $filter];
            }
        }
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function includes(): iterable
    {
        foreach (self::INCLUDES as $model => $includes) {
            foreach ($includes as $include) {
                yield self::short($model) . " include={$include}" => [$model, $include];
            }
        }
    }

    #[DataProvider('filters')]
    public function testTheSameRows(string $model, string $filter): void
    {
        [$reference, $candidate] = self::both(static fn () => Ask::rows($model, $filter));

        $this->assertSame(self::json($reference), self::json($candidate), self::short($model) . " filter={$filter}");
    }

    #[DataProvider('filters')]
    public function testTheSameCount(string $model, string $filter): void
    {
        [$reference, $candidate] = self::both(static fn () => Ask::count($model, $filter));

        $this->assertSame($reference, $candidate, self::short($model) . " count filter={$filter}");
    }

    #[DataProvider('filters')]
    public function testTheSamePage(string $model, string $filter): void
    {
        [$reference, $candidate] = self::both(static fn () => Ask::rows($model, $filter, 'id:desc', null, 2, 1));

        $this->assertSame(self::json($reference), self::json($candidate), self::short($model) . " page filter={$filter}");
    }

    #[DataProvider('includes')]
    public function testTheSameIncludedRelations(string $model, string $include): void
    {
        [$reference, $candidate] = self::both(static fn () => Ask::rows($model, '', 'id:asc', $include));

        $this->assertSame(self::json($reference), self::json($candidate), self::short($model) . " include={$include}");
    }

    private static function json(mixed $answer): string
    {
        return (string) json_encode($answer, JSON_PRETTY_PRINT);
    }

    private static function short(string $model): string
    {
        return substr(strrchr($model, '\\') ?: $model, 1);
    }
}
