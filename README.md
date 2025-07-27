# MTS Tariff Price Parser

[English](#english) | [Русский](#russian)

<a name="english"></a>
# MTS Tariff Price Parser (English)

A parser for MTS mobile operator tariff prices across different regions of Russia.

## Description

This tool collects information about MTS tariffs from all regional websites, extracts pricing data, and saves it to a single JSON file. It also displays information about the cheapest regions for each tariff.

## Requirements

- PHP 8.0 or higher
- Composer

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/eav93/mts-tariffs.git
   cd mts-tariffs
   ```

2. Install dependencies using Composer:
   ```
   composer install
   ```

## Usage

### Running the parser

To run the parser, execute:

```
php parse.php
```

### Command line options

- `--refresh-cache`: Force refresh of all cached data

Example:
```
php parse.php --refresh-cache
```

## Results

The parser creates the following files:

- `json/*.json`: Cached tariff data for each region
- `result.json`: Consolidated tariff pricing data across all regions

### Output format

During operation, the parser outputs information about its progress. Upon completion, it displays a list of the cheapest regions for each tariff in the format:

```
Tariff 'tariff-name': XXX RUB - Cheapest in regions: region1, region2, ...
```

## Project structure

- `parse.php`: Main parser script
- `json/`: Directory for caching tariff data
- `result.json`: Final file with pricing data

## Notes

- The parser uses caching to reduce load on MTS websites
- To update cached data, use the `--refresh-cache` option

---

<a name="russian"></a>
# Парсер цен тарифов МТС (Русский)

Парсер цен тарифов мобильного оператора МТС для различных регионов России.

## Описание

Этот инструмент собирает информацию о тарифах МТС со всех региональных сайтов, извлекает данные о ценах и сохраняет их в единый JSON-файл. Также выводит информацию о самых дешевых регионах для каждого тарифа.

## Требования

- PHP 8.0 или выше
- Composer

## Установка

1. Клонируйте репозиторий:
   ```
   git clone https://github.com/eav93/mts-tariffs.git
   cd mts-tariffs
   ```

2. Установите зависимости с помощью Composer:
   ```
   composer install
   ```

## Использование

### Запуск парсера

Для запуска парсера выполните:

```
php parse.php
```

### Опции командной строки

- `--refresh-cache`: Принудительное обновление всех кэшированных данных

Пример:
```
php parse.php --refresh-cache
```

## Результаты

Парсер создает следующие файлы:

- `json/*.json`: Кэшированные данные о тарифах для каждого региона
- `result.json`: Консолидированные данные о ценах на тарифы во всех регионах

### Формат вывода

В процессе работы парсер выводит информацию о ходе выполнения. По завершении работы выводится список самых дешевых регионов для каждого тарифа в формате:

```
Tariff 'название-тарифа': XXX RUB - Cheapest in regions: регион1, регион2, ...
```

## Структура проекта

- `parse.php`: Основной скрипт парсера
- `json/`: Директория для кэширования данных о тарифах
- `result.json`: Итоговый файл с данными о ценах

## Примечания

- Парсер использует кэширование для уменьшения нагрузки на сайты МТС
- Для обновления кэшированных данных используйте опцию `--refresh-cache`