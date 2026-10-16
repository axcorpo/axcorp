<?php

namespace yii\helpers;

/**
 * Html provides a set of static methods for generating commonly used HTML tags.
 *
 * Nearly all of the methods in this class allow setting additional html attributes for the html
 * tags they generate. You can specify, for example, `class`, `style` or `id` for an html element
 * using the `$options` parameter. See the documentation of the [[tag()]] method for more details.
 *
 * For more details and usage information on Html, see the [guide article on html helpers](guide:helper-html).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alin Hort <alinhort@gmail.com>
 * @since 2.0
 */
class Html extends BaseHtml
{
	/**
	 * @inheritdoc
	 */
	protected static function booleanInput($type, $name, $checked = false, $options = [])
	{
		// Add an empty element afer the label - needed for CSS styling
		if (isset($options['label'])) {
			$options['label'] = $options['label'] . '<span></span>';
		}
		// Return the parent result
		return parent::booleanInput($type, $name, $checked, $options);
	}
}