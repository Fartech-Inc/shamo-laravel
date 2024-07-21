<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductGalleryResource\Pages;
use App\Filament\Resources\ProductGalleryResource\RelationManagers;
use App\Models\ProductGallery;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductGalleryResource extends Resource
{
    protected static ?string $model = ProductGallery::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('products_id')
                ->relationship('product', 'name')
                ->required()
                ->label('Product Name'),
                TextInput::make('url')
                ->label('url'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                ->label('Product Name'),
                TextColumn::make('url')
                ->label('url')
                ->url(fn ($record) => $record->url, true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductGalleries::route('/'),
            'create' => Pages\CreateProductGallery::route('/create'),
            'edit' => Pages\EditProductGallery::route('/{record}/edit'),
        ];
    }
}
