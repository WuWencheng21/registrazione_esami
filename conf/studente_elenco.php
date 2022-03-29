<?php $this->layout('home', ['title' => 'Elenco studenti']) ?>
<h1><b>Elenco soci</h1></b>
<div class="form-gruop">
    <div class="col-10 col-sm-36">
        <table class="table">
            <thead>
            <tr>
                <th>ID studente</th>
                <th>Nome</th>
                <th>Cognome</th>
                <th>Matricola</th>
                <th>Voto</th>
                <th>Indirizzo</th>
            </tr>
            </thead>
            <?php foreach ($studente as $studente): ?>
                <tbody>
                <tr>
                    <td><?=$studente['id']?></td>
                    <td><?=$studente['nome']?></td>
                    <td><?=$studente['cognome']?></td>
                    <td><?=$studente['matricola']?></td>
                    <td><?=$studente['voto']?></td>
                    <td><?=$studente['id_indirizzo']?></td>
                    <td><a href="modifica.php?id=<?=$studente['id']?>"><i class="icon icon-edit"></i></a></td>
                    <td><a href="elimina.php?id=<?=$studente['id']?>"><i class="icon icon-delete"></i></a> </td>
                </tr>
                </tbody>
            <?php endforeach; ?>
        </table>
        <br>
        <form action="<?=$basepath?>/studente/aggiungi" method="post" class="form-horizontal">
            <p><input type="submit" value="Aggiungi studente"></p>
        </form>