<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mohebifar\DateTimeBundle\Form\Transformer;

use Mohebifar\DateTimeBundle\Calendar\Proxy;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer;

/**
 * Transforms between a normalized time and a localized time string/array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Mohamad Mohebifar <info@mohebifar.ir>
 */
class DateTimeToArrayTransformer extends BaseDateTimeTransformer
{
    private $pad;

    private $fields;

    /**
     * @var Service
     */
    private $date;

    /**
     * Constructor.
     *
     * @param Proxy $date
     * @param string  $inputTimezone  The input timezone
     * @param string  $outputTimezone The output timezone
     * @param array   $fields         The date fields
     * @param Boolean $pad            Whether to use padding
     *
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct(Proxy $date, $inputTimezone = null, $outputTimezone = null, array $fields = null, $pad = false)
    {
        $this->date = $date;

        parent::__construct($inputTimezone, $outputTimezone);

        if (null === $fields) {
            $fields = array('year', 'month', 'day', 'hour', 'minute', 'second');
        }

        $this->fields = $fields;
        $this->pad = (Boolean) $pad;
    }

    /**
     * Transforms a normalized date into a localized date.
     *
     * @param \DateTime $dateTime Normalized date.
     *
     * @return array Localized date.
     *
     * @throws TransformationFailedException If the given value is not an
     *                                       instance of \DateTime or if the
     *                                       output timezone is not supported.
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return array_intersect_key(array(
                'year'    => '',
                'month'   => '',
                'day'     => '',
                'hour'    => '',
                'minute'  => '',
                'second'  => '',
            ), array_flip($this->fields));
        }

        if (!$dateTime instanceof \DateTime) {
            throw new TransformationFailedException('Expected a \DateTime.');
        }

        $dateTime = clone $dateTime;
        if ($this->inputTimezone !== $this->outputTimezone) {
            try {
                $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
            } catch (\Exception $e) {
                throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
            }
        }


        $result = array_intersect_key(array(
            'year'    => $this->date->format('Y', $dateTime),
            'month'   => $this->date->format('m', $dateTime),
            'day'     => $this->date->format('d', $dateTime),
            'hour'    => $dateTime->format('H'),
            'minute'  => $dateTime->format('i'),
            'second'  => $dateTime->format('s'),
        ), array_flip($this->fields));

        if (!$this->pad) {
            foreach ($result as &$entry) {
                // remove leading zeros
                $entry = (string) (int) $entry;
            }
        }

        return $result;
    }

    /**
     * Transforms a localized date into a normalized date.
     *
     * @param array $value Localized date
     *
     * @return \DateTime Normalized date
     *
     * @throws TransformationFailedException If the given value is not an array,
     *                                       if the value could not be transformed
     *                                       or if the input timezone is not
     *                                       supported.
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        if ( $value['year'] == 0 || $value['month'] == 0 || $value['day'] == 0 )
            return null;

        if ('' === implode('', $value)) {
            return null;
        }

        $emptyFields = array();

        foreach ($this->fields as $field) {
            if (!isset($value[$field])) {
                $emptyFields[] = $field;
            }
        }

        if (count($emptyFields) > 0) {
            throw new TransformationFailedException(
                sprintf('The fields "%s" should not be empty', implode('", "', $emptyFields)
            ));
        }

        if (isset($value['month']) && !ctype_digit($value['month']) && !is_int($value['month'])) {
            throw new TransformationFailedException('This month is invalid');
        }

        if (isset($value['day']) && !ctype_digit($value['day']) && !is_int($value['day'])) {
            throw new TransformationFailedException('This day is invalid');
        }

        if (isset($value['year']) && !ctype_digit($value['year']) && !is_int($value['year'])) {
            throw new TransformationFailedException('This year is invalid');
        }

        if (!empty($value['month']) && !empty($value['day']) && !empty($value['year']) && false === checkdate($value['month'], $value['day'], $value['year'])) {
            throw new TransformationFailedException('This is an invalid date');
        }
        $value['hour'] = empty($value['hour']) ? '0' : $value['hour'];
        $value['minute'] = empty($value['minute']) ? '0' : $value['minute'];
        $value['second'] = empty($value['second']) ? '0' : $value['second'];
        try {
            $dateTime = $this->date->makeTime($value['hour'], $value['minute'], $value['second'], $value['month'], $value['day'], $value['year']);

            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
