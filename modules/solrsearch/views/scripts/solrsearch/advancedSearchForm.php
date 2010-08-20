<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    TODO
 * @author      Julian Heise <heise@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */
?>

<form action="<?= $this->url(array('module'=>'solrsearch','controller'=>'solrsearch','action'=>'searchdispatch')); ?>" method="post">

    <fieldset>
        <legend>Allgemeine Suchoptionen</legend>
        <label for="default_operator">Suchergebnisse enthalten</label>
        <select name="defaultoperator" id="default_operator">
            <option value="AND" >alle Begriffe</option>
            <option value="OR" >mindestens einen Begriff</option>
        </select>

        <br/>

        <label for="hits_per_page">Treffer pro Seite</label>
        <select name="hits_per_page" id="hits_per_page">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
    </fieldset>

    <fieldset>
        <legend>Suchfelder</legend>

        <table>
            <tr>
                <td>
                    <label for="author">Autor</label>
                </td>
                <td>
                    <input type="text" id="author" name="author" value="" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="title">Titel</label>
                </td>
                <td>
                    <input type="text" id="title" name="title" value="" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="abstract">Volltext</label>
                </td>
                <td>
                    <input type="text" id="abstract" name="abstract" value="" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="year">Erscheinungsjahr</label>
                </td>
                <td>
                    <input type="text" id="year" name="year" value="" />
                </td>
            </tr>
        </table>

    </fieldset>

    <input type="submit" value="Suchen" />

    <input type="hidden" name="searchtype" value="advanced" />
    <input type="hidden" name="start" value="0" />
    <input type="hidden" name="sortfield" value="score" />
    <input type="hidden" name="sordorder" value="desc" />

</form>