// @ts-check
const path = require('node:path');

/**
 * Where the owner's saved session lives (git-ignored with test-results/).
 */
module.exports = {
  OWNER_STATE: path.join(__dirname, '..', '..', 'test-results', '.auth', 'owner.json'),
};
