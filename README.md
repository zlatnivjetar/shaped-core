# Important context

### 1. In production folder are 3 plugins with poora architecture. They will be merged into 1, **shaped-core-refactored**. refactored folder contains the current progress.

### 2. The final plugin should follow shaped-core-architecture.md

### 3. _scratch folder is not present in production in this format, but it's scattered across elementor custom code, custom css, code snippets etc. It has to be properly integrated. Checkout.js is very important, as it contains the custom urgency/discount logic for both search results and checkout pages

## Other notes

I believe a file in roomcloud-integration depends on Shaped_Pricing class. That class should remain, but also be stripped of any seasonal pricing logic, because Motopress handles that.

## To-do (Claude code)
1. Check the mentioned Shaped_Pricing class in refactored version to verify it is not blocking the Roomcloud plugin
2. Determine if everything from _scratch folder is properly integrated (exclude /css/elementor folder)
3. Create the files missing in refactor outlined in architecture file
