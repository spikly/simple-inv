<?php

$projects = fetchProjects();

?>

<div class="flex-nav">

    <h2>Projects</h2>

    <nav class="onpage-nav">
        <a href="index.php?page=add-project">
            Add Project
        </a>
    </nav>

</div>


<?php if(count($projects) > 0): ?>

<div class="table-container">

    <table>

        <tr>
            <th>Project</th>
            <th>Reference</th>
            <th>Status</th>
            <th>Assemblies</th>
            <th>Required</th>
            <th>Installed</th>
            <th></th>
        </tr>

        <?php foreach($projects as $project): ?>

            <?php
            $summary = getProjectSummary(
                $project['project_id']
            );
            ?>

            <tr>

                <td>
                    <a href="index.php?page=view-project&project_id=<?php
                        echo (int)$project['project_id'];
                    ?>">
                        <?php
                        echo escapeHtml(
                            $project['project_name']
                        );
                        ?>
                    </a>
                </td>

                <td>
                    <?php
                    echo escapeHtml(
                        $project['project_reference']
                    );
                    ?>
                </td>

                <td>
                    <?php
                    echo escapeHtml(
                        $project['project_status_name']
                    );
                    ?>
                </td>

                <td>
                    <?php
                    echo (int)$project['assembly_count'];
                    ?>
                </td>

                <td>
                    <?php
                    echo (float)$summary['required_quantity'];
                    ?>
                </td>

                <td>
                    <?php
                    echo (float)$summary['installed_quantity'];
                    ?>
                </td>

                <td>
                    <a href="index.php?page=edit-project&project_id=<?php
                        echo (int)$project['project_id'];
                    ?>">
                        Edit
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

    </table>

</div>

<?php else: ?>

<p>No projects have been created.</p>

<?php endif; ?>