# Category Image Mapping – Your Input Needed

All 55 category images from Johnny's Selected Seeds (vegetables) have been downloaded to:

**`shared/category_images/johnnys/`**

## What’s Done

1. Downloaded 55 images (54 via script + 1 leeks manually).
2. Added mapping script: `python3 shared/data/map_category_images.py --use-mapping`
3. Your categories (from `image/catalog/categories/`) use IDs: 100, 101, 102, ... 500.

## Our Categories That Need Mapping

These category IDs have no `johnnys_slug` yet. Add a slug in `shared/data/category_mapping.csv` to map each to a Johnny's image:

| our_category_id | johnnys_slug (you fill) |
|-----------------|---------------------------|
| 100 | |
| 101 | |
| 102 | |
| 103 | |
| 110 | |
| 120 | |
| 121 | |
| 122 | |
| 123 | |
| 130 | |
| 140 | |
| 141 | |
| 142 | |
| 150 | |
| 160 | |
| 170 | |
| 180 | |
| 200 | |
| 210 | |
| 220 | |
| 230 | |
| 240 | |
| 250 | |
| 260 | |
| 270 | |
| 280 | |
| 281 | |
| 282 | |
| 290 | |
| 300 | |
| 310 | |
| 320 | |
| 330 | |
| 331 | |
| 332 | |
| 400 | |
| 402 | |
| 403 | |
| 404 | |
| 405 | |
| 406 | |
| 407 | |
| 408 | |
| 500 | |

## Johnny's Images Available (slug → name)

Use these slugs in `category_mapping.csv`:

| slug | name |
|------|------|
| artichokes | Artichokes |
| asparagus | Asparagus |
| beans | Beans |
| beets | Beets |
| broccoli | Broccoli |
| brussels-sprouts | Brussels Sprouts |
| burdock | Burdock |
| cabbage | Cabbage |
| cardoon | Cardoon |
| carrots | Carrots |
| cauliflower | Cauliflower |
| celery-and-celeriac | Celery and Celeriac |
| chicory | Chicory |
| chinese-cabbage | Chinese Cabbage |
| collards | Collards |
| corn | Corn |
| cucumbers | Cucumbers |
| eggplant | Eggplant |
| fennel | Fennel |
| garlic | Garlic |
| gourds | Gourds |
| greens | Greens |
| horseradish | Horseradish |
| husk-cherry | Husk Cherry |
| kale | Kale |
| kalettes | Kalettes |
| kohlrabi | Kohlrabi |
| leeks | Leeks |
| lettuce | Lettuce |
| melons | Melons |
| microgreens | Microgreens |
| mushrooms | Mushrooms |
| okra | Okra |
| onions | Onions |
| parsnips | Parsnips |
| peas | Peas |
| peppers | Peppers |
| potatoes | Potatoes |
| pumpkins | Pumpkins |
| radishes | Radishes |
| rutabagas | Rutabagas |
| salsify | Salsify |
| scorzonera | Scorzonera |
| shallots | Shallots |
| shoots | Shoots |
| spinach | Spinach |
| sprouts | Sprouts |
| squash | Squash |
| sweet-potatoes | Sweet Potatoes |
| swiss-chard | Swiss Chard |
| tomatillos | Tomatillos |
| tomatoes | Tomatoes |
| turnips | Turnips |
| watermelons | Watermelons |
| zucchini | Zucchini |

## How to Complete

1. Open `shared/data/category_mapping.csv`.
2. For each `our_category_id`, add the `johnnys_slug` from the table above.
3. Run:
   ```bash
   python3 shared/data/map_category_images.py --use-mapping
   ```
4. Apply the generated SQL to your database:
   ```bash
   mysql -u ... -p ... < shared/data/category_image_updates.sql
   ```

Example mapping row: `100,tomatoes` (category 100 = Tomatoes).
