<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
    <div>
        {{line}}
    </div>

</template>

<script>

    /**
     * @Copyright MIKA, Nicholas Eede, 2006, "Relax, take it easy"
     */
    const lyrics = ('Relax, take it easy \n' +
        'For there is nothing that we can do. \n' +
        'Relax, take it easy \n' +
        'Blame it on me or blame it on you.\n' +
        'Took a right to the end of the line \n' +
        'Where no one ever goes. \n' +
        'Ended up on a broken train with nobody I know. \n' +
        'But the pain and the (longings) the same. \n' +
        'Where the dying \n' +
        'Now I\'m lost and I\'m screaming for help.\n' +
        'Relax, take it easy \n' +
        'For there is nothing that we can do. \n' +
        'Relax, take it easy \n' +
        'Blame it on me or blame it on you.\n' +
        'It\'s as if I\'m scared. \n' +
        'It\'s as if I\'m terrified. \n' +
        'It\'s as if I scared. \n' +
        'It\'s as if I\'m playing with fire. \n' +
        'Scared. \n' +
        'It\'s as if I\'m terrified. \n' +
        'Are you scared\' \n' +
        'Are we playing with fire\'\n' +
        'Relax \n' +
        'There is an answer to the darkest times. \n' +
        'It\'s clear we don\'t understand but the last thing on my mind \n' +
        'Is to leave you. \n' +
        'I believe that we\'re in this together. \n' +
        'Don\'t scream \' there are so many roads left.').split('\n');

    export default {

        props: [
            'eventId'
        ],

        mounted() {

            this.position = 0;

            this.interval = setInterval(
                () => {
                    this.position = (this.position + 1) % lyrics.length;
                    this.line = lyrics[this.position];
                },
                5000
            );

            this.ine = lyrics[0];

        },

        destroyed() {
            clearInterval(this.interval);
        },

        data() {
            return {
                position: 0,
                line: lyrics[0]
            }
        },
    }
</script>