<?php

/**
 * File containing the eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentTypeGroupId class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\API\Repository\Values\Content\Query\Criterion;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator\Specifications;
use eZ\Publish\API\Repository\Values\Content\Query\CriterionInterface;

/**
 * A criterion that will match content based on its ContentTypeGroup id.
 * The ContentType must belong to at least one of the matched ContentTypeGroups.
 *
 * Supported operators:
 * - IN: will match from a list of ContentTypeGroup id
 * - EQ: will match against one ContentTypeGroup id
 */
class ContentTypeGroupId extends Criterion implements CriterionInterface
{
    /**
     * Creates a new ContentTypeGroup criterion.
     *
     * Content will be matched if it matches one of the contentTypeGroupId in $value
     *
     * @param int|int[] $value One or more contentTypeGroupId that must be matched
     *
     * @throws \InvalidArgumentException if the parameters don't match what the criterion expects
     */
    public function __construct($value)
    {
        parent::__construct(null, null, $value);
    }

    public function getSpecifications()
    {
        $types = Specifications::TYPE_INTEGER | Specifications::TYPE_STRING;

        return array(
            new Specifications(
                Operator::IN,
                Specifications::FORMAT_ARRAY,
                $types
            ),
            new Specifications(
                Operator::EQ,
                Specifications::FORMAT_SINGLE,
                $types
            ),
        );
    }

    public static function createFromQueryBuilder($target, $operator, $value)
    {
        return new self($value);
    }
}
